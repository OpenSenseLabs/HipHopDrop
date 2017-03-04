<?hh

namespace Drupal\Tests\migrate\Kernel\process;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\KernelTests\Core\File\FileTestBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\process\FileCopy;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\migrate\Row;

/**
 * Tests the file_copy process plugin.
 *
 * @coversDefaultClass \Drupal\migrate\Plugin\migrate\process\FileCopy
 *
 * @group migrate
 */
class FileCopyTest extends FileTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['migrate', 'system'];

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->fileSystem = $this->container->get('file_system');
    $this->container->get('stream_wrapper_manager')->registerWrapper('temporary', 'Drupal\Core\StreamWrapper\TemporaryStream', StreamWrapperInterface::LOCAL_NORMAL);
  }

  /**
   * Test successful imports/copies.
   */
  public function testSuccessfulCopies() {
    $file = $this->createUri(NULL, NULL, 'temporary');
    $file_absolute = $this->fileSystem->realpath($file);
    $data_sets = [
      // Test a local to local copy.
      [
        $this->root . '/core/modules/simpletest/files/image-test.jpg',
        'public://file1.jpg'
      ],
      // Test a temporary file using an absolute path.
      [
        $file_absolute,
        'temporary://test.jpg'
      ],
      // Test a temporary file using a relative path.
      [
        $file_absolute,
        'temporary://core/modules/simpletest/files/test.jpg'
      ],
    ];
    foreach ($data_sets as $data) {
      list($source_path, $destination_path) = $data;
      $actual_destination = $this->doTransform($source_path, $destination_path);
      $message = sprintf('File %s exists', $destination_path);
      $this->assertFileExists($destination_path, $message);
      // Make sure we didn't accidentally do a move.
      $this->assertFileExists($source_path, $message);
      $this->assertSame($actual_destination, $destination_path, 'The import returned the copied filename.');
    }
  }

  /**
   * Test successful moves.
   */
  public function testSuccessfulMoves() {
    $file_1 = $this->createUri(NULL, NULL, 'temporary');
    $file_1_absolute = $this->fileSystem->realpath($file_1);
    $file_2 = $this->createUri(NULL, NULL, 'temporary');
    $file_2_absolute = $this->fileSystem->realpath($file_2);
    $local_file = $this->createUri(NULL, NULL, 'public');
    $data_sets = [
      // Test a local to local copy.
      [
        $local_file,
        'public://file1.jpg'
      ],
      // Test a temporary file using an absolute path.
      [
        $file_1_absolute,
        'temporary://test.jpg'
      ],
      // Test a temporary file using a relative path.
      [
        $file_2_absolute,
        'temporary://core/modules/simpletest/files/test.jpg'
      ],
    ];
    foreach ($data_sets as $data) {
      list($source_path, $destination_path) = $data;
      $actual_destination = $this->doTransform($source_path, $destination_path, ['move' => TRUE]);
      $message = sprintf('File %s exists', $destination_path);
      $this->assertFileExists($destination_path, $message);
      $message = sprintf('File %s does not exist', $source_path);
      $this->assertFileNotExists($source_path, $message);
      $this->assertSame($actual_destination, $destination_path, 'The importer returned the moved filename.');
    }
  }

  /**
   * Test that non-existent files throw an exception.
   *
   * @expectedException \Drupal\migrate\MigrateException
   *
   * @expectedExceptionMessage File '/non/existent/file' does not exist
   */
  public function testNonExistentSourceFile() {
    $source = '/non/existent/file';
    $this->doTransform($source, 'public://wontmatter.jpg');
  }

  /**
   * Tests that non-writable destination throw an exception.
   *
   * @covers ::transform
   */
  public function testNonWritableDestination() {
    $source = $this->createUri('file.txt', NULL, 'temporary');

    // Create the parent location.
    $this->createDirectory('public://dir');

    // Copy the file under public://dir/subdir1/.
    $this->doTransform($source, 'public://dir/subdir1/file.txt');

    // Check that 'subdir1' was created and the file was successfully migrated.
    $this->assertFileExists('public://dir/subdir1/file.txt');

    // Remove all permissions from public://dir to trigger a failure when
    // trying to create a subdirectory 'subdir2' inside public://dir.
    $this->fileSystem->chmod('public://dir', 0);

    // Check that the proper exception is raised.
    $this->setExpectedException(MigrateException::class, "Could not create or write to directory 'public://dir/subdir2'");
    $this->doTransform($source, 'public://dir/subdir2/file.txt');
  }

  /**
   * Test the 'rename' overwrite mode.
   */
  public function testRenameFile() {
    $source = $this->createUri(NULL, NULL, 'temporary');
    $destination = $this->createUri('foo.txt', NULL, 'public');
    $expected_destination = 'public://foo_0.txt';
    $actual_destination = $this->doTransform($source, $destination, ['rename' => TRUE]);
    $this->assertFileExists($expected_destination, 'File was renamed on import');
    $this->assertSame($actual_destination, $expected_destination, 'The importer returned the renamed filename.');
  }

  /**
   * Tests that remote URIs are delegated to the download plugin.
   */
  public function testDownloadRemoteUri() {
    $download_plugin = $this->getMock(MigrateProcessInterface::class);
    $download_plugin->expects($this->once())->method('transform');

    $plugin = new FileCopy(
      [],
      $this->randomMachineName(),
      [],
      $this->container->get('stream_wrapper_manager'),
      $this->container->get('file_system'),
      $download_plugin
    );

    $plugin->transform(
      ['http://drupal.org/favicon.ico', '/destination/path'],
      $this->getMock(MigrateExecutableInterface::class),
      new Row([], []),
      $this->randomMachineName()
    );
  }

  /**
   * Do an import using the destination.
   *
   * @param string $source_path
   *   Source path to copy from.
   * @param string $destination_path
   *   The destination path to copy to.
   * @param array $configuration
   *   Process plugin configuration settings.
   *
   * @return string
   *   The URI of the copied file.
   */
  protected function doTransform($source_path, $destination_path, $configuration = []) {
    $plugin = FileCopy::create($this->container, $configuration, 'file_copy', []);
    $executable = $this->prophesize(MigrateExecutableInterface::class)->reveal();
    $row = new Row([], []);

    return $plugin->transform([$source_path, $destination_path], $executable, $row, 'foobaz');
  }

}
