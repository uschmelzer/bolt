<?php
namespace Bolt\Tests\Extensions\Mock;

use Pimple as Container;

/**
 * Class to test correct operation and locations of composer configuration.
 *
 * @author Ross Riley <riley.ross@gmail.com>
 */
class BadExtension extends Extension
{
    public function initialize(Container $app)
    {
        throw new \Exception('BadExtension', 1);
    }

    public function getSnippets()
    {
        throw new \Exception('BadExtensionSnippets', 1);
    }

    public function getName()
    {
        return 'badextension';
    }
}
