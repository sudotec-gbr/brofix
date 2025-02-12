<?php

declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks\LinkTargetCache;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Sypets\Brofix\Linktype\ErrorParams;

interface LinkTargetCacheInterface
{
    public function setExpire(int $expire): void;

    /**
     * Generate UrlResponse array from arguments.
     *
     * @param bool $isValid
     * @param ErrorParams $errorParams
     * @return array{'valid': bool, 'errorParams': array<mixed>}
     */
    public function generateUrlResponse(bool $isValid, ErrorParams $errorParams): array;

    /**
     * Check if url exists in link cache (and is not expired)
     */
    public function hasEntryForUrl(string $linkTarget, string $linkType, bool $useExpire = true, int $expire = 0): bool;

    /**
     * Get result of link check
     *
     * @param string $linkTarget
     * @param string $linkType
     * @param int $expire (optional, default is 0, in that case uses $this->expire)
     * @return mixed[]
     */
    public function getUrlResponseForUrl(string $linkTarget, string $linkType, int $expire = 0): array;

    /**
     * @param string $linkTarget
     * @param string $linkType
     * @param mixed[] $urlResponse
     */
    public function setResult(string $linkTarget, string $linkType, array $urlResponse): void;

    public function remove(string $linkTarget, string $linkType): void;
}
