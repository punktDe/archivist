<?php
declare(strict_types=1);

namespace PunktDe\Archivist\Eel;

/*
 * This file is part of the PunktDe.Archivist package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Behat\Transliterator\Transliterator;
use Neos\Eel\ProtectedContextAwareInterface;

class ArchivistHelper implements ProtectedContextAwareInterface
{

    /**
     * @param string $string
     * @param int $position
     * @param int $length
     * @return string
     */
    public function buildSortingCharacter(?string $string, int $position = 0, int $length = 1): string
    {
        if ($string === null) {
            return '#';
        }

        $character = mb_substr($string, $position, $length);

        // Transliterate (transform 北京 to 'Bei Jing')
        $character = Transliterator::transliterate($character);

        // Ensure only allowed characters are left
        $character = preg_replace('/[^a-z]/', '#', $character);

        if ($character === '') {
            $character = '#';
        }

        return strtoupper($character);
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
