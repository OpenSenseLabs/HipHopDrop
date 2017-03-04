<?hh

namespace Drupal\system\Tests\FileTransfer;

/**
 * Mock connection object for test case.
 */
class MockTestConnection {

  protected $commandsRun = array();
  public $connectionString;

  public function run($cmd) {
    $this->commandsRun[] = $cmd;
  }

  public function flushCommands() {
    $out = $this->commandsRun;
    $this->commandsRun = array();
    return $out;
  }

}
