<?php

namespace Atas\SsgSystemPhp;

/**
 * This function reads the posts directory to generate posts.json file
 * This way we are not compiling every .md file to read its meta data (front matter)
 * If any post's last modified time is newer than the cached posts.json file, it will be updated
 */
class PostsCache {
    private string $cacheFilePath;
    private string $postsDir;

    /* @var Post[] */
    private array $cachedPosts = [];
    private Markdown $markdown;

    public function __construct(string $cacheFilePath, string $postsDir, Markdown $markdown) {
        $this->cacheFilePath = $cacheFilePath;
        $this->postsDir = $postsDir;

        if (file_exists($this->cacheFilePath)) {
            $this->cachedPosts = json_decode(file_get_contents($this->cacheFilePath));
        }
        $this->markdown = $markdown;
    }

    public function updateAndGetCachedPostList(): array {
        $cacheModified = false;

        $postFiles = scandir($this->postsDir);
        foreach ($postFiles as $postFile) {
            if (!str_ends_with($postFile, ".md")) continue;
            if ($this->shouldUpdateCache($postFile)) {
                $this->updateCache($postFile);
                $cacheModified = true;
            }
        }

        $this->sortPosts();

        if ($cacheModified) {
            $this->saveCache();
        }

        return $this->cachedPosts;
    }

    private function shouldUpdateCache(string $postFile): bool {
        if (is_file($this->postsDir . $postFile)) {
            $lastModified = filemtime($this->postsDir . $postFile);
            foreach ($this->cachedPosts as $cachedPost) {
                if ($cachedPost->filename === $postFile && $cachedPost->lastModified >= $lastModified) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    protected function get_markdown(string $path): ConvertedMarkdown
    {
        return $this->markdown->convert($path);
    }

    private function updateCache(string $postFile): void {
        $tpl = $this->get_markdown($this->postsDir . $postFile);

        $postObj = new Post();
        $postObj->title = $tpl->meta->title;
        $postObj->desc = $tpl->meta->desc;
        $postObj->slug = $tpl->meta->slug;
        $postObj->filename = $postFile;
        $postObj->lastModified = filemtime($this->postsDir . $postFile);

        $found = false;
        foreach ($this->cachedPosts as &$cachedPost) {
            if ($cachedPost->filename === $postFile) {
                $cachedPost = $postObj;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->cachedPosts[] = $postObj;
        }
    }

    private function sortPosts(): void {
        usort($this->cachedPosts, function($a, $b) {
            return strcmp($b->filename, $a->filename);
        });
    }

    private function saveCache(): void {
        file_put_contents($this->cacheFilePath, json_encode($this->cachedPosts, JSON_PRETTY_PRINT));
    }
}
