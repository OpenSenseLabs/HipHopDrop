<?hh

namespace Drupal\Core\Render;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;

/**
 * Turns a render array into a placeholder.
 */
class PlaceholderGenerator implements PlaceholderGeneratorInterface {

  /**
   * The renderer configuration array.
   *
   * @var array
   */
  protected $rendererConfig;

  /**
   * Constructs a new Placeholder service.
   *
   * @param array $renderer_config
   *   The renderer configuration array.
   */
  public function __construct(array $renderer_config) {
    $this->rendererConfig = $renderer_config;
  }

  /**
   * {@inheritdoc}
   */
  public function canCreatePlaceholder(array $element) {
    return
      // If generated by a #lazy_builder callback, placeholdering is possible.
      isset($element['#lazy_builder'])
      &&
      // If #create_placeholder === FALSE, placeholdering is disallowed.
      (!isset($element['#create_placeholder']) || $element['#create_placeholder'] !== FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function shouldAutomaticallyPlaceholder(array $element) {
    // Auto-placeholder if the max-age, cache context or cache tag is specified
    // in the auto-placeholder conditions in the 'renderer.config' container
    // parameter.
    $conditions = $this->rendererConfig['auto_placeholder_conditions'];

    if (isset($element['#cache']['max-age']) && $element['#cache']['max-age'] !== Cache::PERMANENT && $element['#cache']['max-age'] <= $conditions['max-age']) {
      return TRUE;
    }

    if (isset($element['#cache']['contexts']) && array_intersect($element['#cache']['contexts'], $conditions['contexts'])) {
      return TRUE;
    }

    if (isset($element['#cache']['tags']) && array_intersect($element['#cache']['tags'], $conditions['tags'])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createPlaceholder(array $element) {
    $placeholder_render_array = array_intersect_key($element, [
      // Placeholders are replaced with markup by executing the associated
      // #lazy_builder callback, which generates a render array, and which the
      // Renderer will render and replace the placeholder with.
      '#lazy_builder' => TRUE,
      // The cacheability metadata for the placeholder. The rendered result of
      // the placeholder may itself be cached, if [#cache][keys] are specified.
      '#cache' => TRUE,
    ]);

    // Generate placeholder markup. Note that the only requirement is that this
    // is unique markup that isn't easily guessable. The #lazy_builder callback
    // and its arguments are put in the placeholder markup solely to simplify<<<
    // debugging.
    $callback = $placeholder_render_array['#lazy_builder'][0];
    $arguments = UrlHelper::buildQuery($placeholder_render_array['#lazy_builder'][1]);
    $token = hash('crc32b', serialize($placeholder_render_array));
    $placeholder_markup = '<drupal-render-placeholder callback="' . Html::escape($callback) . '" arguments="' . Html::escape($arguments) . '" token="' . Html::escape($token) . '"></drupal-render-placeholder>';

    // Build the placeholder element to return.
    $placeholder_element = [];
    $placeholder_element['#markup'] = Markup::create($placeholder_markup);
    $placeholder_element['#attached']['placeholders'][$placeholder_markup] = $placeholder_render_array;
    return $placeholder_element;
  }

}
