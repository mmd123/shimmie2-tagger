<?php

declare(strict_types=1);

namespace Shimmie2;

class TagImporter extends Extension
{
    public function onDataUpload(DataUploadEvent $event)
    {
        foreach ($images as $_ => $image) {
            if (in_array('tagme', $image->get_tag_array())) {
                $this->fetch_and_update_tags($image);
            }
        }
    }

    public function onTagSet(TagSetEvent $event)
    {
        if (in_array('tagme', $event->new_tags)) {
            $this->fetch_and_update_tags($event->image);
        }
    }

    private function fetch_and_update_tags(Image $image)
    {
        log_debug("tag_importer", "Fetching tags for image $image->hash...");
        $all_tags = [];

        array_push($all_tags, ...$this->fetch_e621_tags($image->hash));
        array_push($all_tags, ...$this->fetch_gelbooru_tags($image->hash));

        $count = count($all_tags);

        log_debug("tag_importer", "Found $count total (unique) tags.");

        if ($count > 0) {
            send_event(new TagSetEvent($image, $all_tags));
        }
    }

    private function set_source(Image $image, string $source)
    {
        send_event(new SourceSetEvent($image, $source));
    }

    private function set_source_if_not_set(Image $image, string $source)
    {
        if (!$this->has_source($image)) {
            log_debug("tag_importer", "Setting image source...");
            $this->set_source($image, $source);
        } else {
            log_debug("tag_importer", "Image source already set.");
        }
    }

    private function has_source(Image $image): bool
    {
        return $image->get_source() != "";
    }

    private function fetch_e621_tags(string $hash): array
    {
        log_debug("tag_importer", "Fetching e621 tags...");
        try {
            $ch = curl_init("https://e621.net/posts.json?tags=MD5:$hash&limit=1");
            curl_setopt($ch, CURLOPT_USERAGENT, "Shimmie2 Tag Importer Extension (by https://github.com/HeCorr)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HEADER, 0);

            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);

            curl_close($ch);

            if ($errno) {
                log_error("tag_importer", "e621 request error: $error");
                return [];
            }

            log_debug("tag_importer", "e621 response code: $code");

            $json = json_decode($data, true);
            $tags = [];

            if (!count($json["posts"])) {
                log_info("tag_importer", "Image $hash not found in e621.");
                return [];
            }

            $this->set_source_if_not_set(Image::by_hash($hash), "https://e621.net/posts/" . strval($json["posts"][0]["id"]));

            foreach ($json["posts"][0]["tags"] as $_ => $category) {
                foreach ($category as $_ => $tag) {
                    // Ignore the "tagme" tag to avoid an infinite loop.
                    if ($tag != "tagme") {
                        array_push($tags, $tag);
                    }
                }
            }

            $count = count($tags);

            if (!$count) {
                log_error("tag_importer", "Failed to parse tags from e621.");
            }

            log_info("tag_importer", "Found $count tags in e621");

            return $tags;
        } catch (\Throwable $th) {
            log_error("tag_importer", "$th");
        }
    }

##YOU WILL NEED TO FILL YOUR OWN API KEY FOR GELBOORU FOR THIS TO WORK. you will ALSO need to fill in your own userID on line 127

    private function fetch_gelbooru_tags(string $hash): array
    {
        log_debug("tag_importer", "Fetching Gelbooru tags...");
        $apiKey = "";
        // TODO: log warning if API key is not set
        try {
            $ch = curl_init("https://gelbooru.com/index.php?page=dapi&s=post&q=index&json=1&limit=1&api_key=$apiKey&user_id=&tags=md5:$hash");
            curl_setopt($ch, CURLOPT_USERAGENT, "Shimmie2 Tag Importer Extension (by https://github.com/HeCorr)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HEADER, 0);

            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);

            curl_close($ch);

            if ($errno) {
                log_error("tag_importer", "Gelbooru request error: $error");
                return [];
            }

            log_debug("tag_importer", "Gelbooru response code: $code");

            $json = json_decode($data, true);
            $tags = [];

            if (!isset($json["post"])) {
                log_info("tag_importer", "Image $hash not found in gelbooru.");
                return [];
            }

            $this->set_source_if_not_set(Image::by_hash($hash), "https://gelbooru.com/index.php?page=post&s=view&id=" . strval($json["post"][0]["id"]));

            foreach (explode(" ", $json["post"][0]["tags"]) as $_ => $tag) {
                // Ignore the "tagme" tag to avoid an infinite loop.
                if ($tag != "tagme") {
                    array_push($tags, $tag);
                }
            }

            $count = count($tags);

            if (!$count) {
                log_error("tag_importer", "Failed to parse tags from Gelbooru.");
            }

            log_info("tag_importer", "Found $count tags in Gelbooru");

            return $tags;
        } catch (\Throwable $th) {
            log_error("tag_importer", "$th");
        }
    }
}
