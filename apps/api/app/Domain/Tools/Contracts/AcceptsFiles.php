<?php

declare(strict_types=1);

namespace App\Domain\Tools\Contracts;

interface AcceptsFiles
{
    /** @return list<string> Allowed MIME types, checked by sniffing bytes — never the declared header. */
    public function acceptedMimeTypes(): array;

    /** Maximum accepted size per file, in bytes. */
    public function maxFileSize(): int;

    /** How many files a single run may accept. */
    public function maxFiles(): int;
}
