<?hh

namespace Drupal\Tests\hal\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Common ancestor for FieldItemNormalizerDenormalizeExceptionsUnitTest and
 * FieldNormalizerDenormalizeExceptionsUnitTest as they have the same
 * dataProvider.
 */
abstract class NormalizerDenormalizeExceptionsUnitTestBase extends UnitTestCase {

  /**
   * Provides data for FieldItemNormalizerDenormalizeExceptionsUnitTest::testFieldItemNormalizerDenormalizeExceptions()
   * and for FieldNormalizerDenormalizeExceptionsUnitTest::testFieldNormalizerDenormalizeExceptions().
   *
   * @return array Test data.
   */
  public function providerNormalizerDenormalizeExceptions() {
    $mock = $this->getMock('\Drupal\Core\Field\Plugin\DataType\FieldItem', array('getParent'));
    $mock->expects($this->any())
      ->method('getParent')
      ->will($this->returnValue(NULL));
    return array(
      array(array()),
      array(array('target_instance' => $mock)),
    );
  }

}
