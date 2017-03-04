<?hh

namespace Drupal\system\Tests\Theme;

use Drupal\simpletest\WebTestBase;

/**
 * Tests that the theme system can be correctly initialized early in the page
 * request.
 *
 * @group Theme
 */
class ThemeEarlyInitializationTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('theme_test');

  /**
   * Test that the theme system can generate output in a request listener.
   */
  public function testRequestListener() {
    $this->drupalGet('theme-test/request-listener');
    // Verify that themed output generated in the request listener appears.
    $this->assertRaw('Themed output generated in a KernelEvents::REQUEST listener');
    // Verify that the default theme's CSS still appears even though the theme
    // system was initialized early.
    $this->assertRaw('classy/css/components/action-links.css');
  }

}
