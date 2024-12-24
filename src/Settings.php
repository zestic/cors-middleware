<?php

/*

Copyright (c) 2022 Pavlo Mikhailidi

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/

declare(strict_types=1);

namespace Zestic\Middleware;

use Override;
use Neomerx\Cors\Strategies\Settings as BaseSettings;

class Settings extends BaseSettings
{
    /** @var array<string> */
    private $allowedOrigins = [];

    #[Override]
    public function setAllowedOrigins(array $origins): BaseSettings
    {
        $this->allowedOrigins = $origins;

        return parent::setAllowedOrigins($origins);
    }

    #[Override]
    public function isRequestOriginAllowed(string $requestOrigin): bool
    {
        $isAllowed = parent::isRequestOriginAllowed($requestOrigin);

        if (! $isAllowed) {
            $isAllowed = $this->wildcardOriginAllowed($requestOrigin);
        }

        return $isAllowed;
    }

    private function wildcardOriginAllowed(string $origin): bool
    {
        foreach ($this->allowedOrigins as $allowedOrigin) {
            if (fnmatch($allowedOrigin, $origin)) {
                return true;
            }
        }

        return false;
    }
}
