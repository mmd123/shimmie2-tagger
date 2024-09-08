<?php

declare(strict_types=1);

namespace Shimmie2;

class TagImporterInfo extends ExtensionInfo
{
    public const KEY = "tag_importer";

    public string $key = self::KEY;
    public string $name = "Tag Importer";
    public string $url = "https://github.com/HeCorr/";
    public ?string $version = "0.4";
    public string $license = self::LICENSE_MIT;
    public array $authors = ["HeCorr" => ""];
    public string $description = "Import image tags from external sites on upload and manual tagging";
    public ?string $documentation = "TODO: add documentation.";
}
