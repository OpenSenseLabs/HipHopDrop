<?hh

namespace Drupal\big_pipe\Render;

use Drupal\Core\Render\HtmlResponse;

/**
 * A response that is sent in chunks by the BigPipe service.
 *
 * Note we cannot use \Symfony\Component\HttpFoundation\StreamedResponse because
 * it makes the content inaccessible (hidden behind a callback), which means no
 * middlewares are able to modify the content anymore.
 *
 * @see \Drupal\big_pipe\Render\BigPipeInterface
 *
 * @internal
 *   This is a temporary solution until a generic response emitter interface is
 *   created in https://www.drupal.org/node/2577631. Only code internal to
 *   BigPipe should instantiate or type hint to this class.
 */
class BigPipeResponse extends HtmlResponse {

  /**
   * The BigPipe service.
   *
   * @var \Drupal\big_pipe\Render\BigPipeInterface
   */
  protected $bigPipe;

  /**
   * Sets the BigPipe service to use.
   *
   * @param \Drupal\big_pipe\Render\BigPipeInterface $big_pipe
   *   The BigPipe service.
   */
  public function setBigPipeService(BigPipeInterface $big_pipe) {
    $this->bigPipe = $big_pipe;
  }

  /**
   * {@inheritdoc}
   */
  public function sendContent() {
    $this->bigPipe->sendContent($this->content, $this->getAttachments());

    return $this;
  }

}
