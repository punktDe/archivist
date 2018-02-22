<?php
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
     * @return string
     */
    public function buildSortingCharacter(?string $string): string
    {
        $firstCharacter = mb_substr($string, 0, 1);

        // Transliterate (transform 北京 to 'Bei Jing')
        $firstCharacter = Transliterator::transliterate($firstCharacter);

        // Ensure only allowed characters are left
        $firstCharacter = preg_replace('/[^a-z]/', '#', $firstCharacter);

        if ($firstCharacter === '') {
            $firstCharacter = '#';
        }

        return strtoupper($firstCharacter);
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
