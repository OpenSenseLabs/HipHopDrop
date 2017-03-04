<?hh // strict

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Exception;

/**
 * Base OutOfBoundsException for the Validator component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OutOfBoundsException extends \OutOfBoundsException implements ExceptionInterface
{
}
