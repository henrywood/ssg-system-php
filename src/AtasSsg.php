<?php

namespace Atas\SsgSystemPhp;

use JetBrains\PhpStorm\NoReturn;
use League\CommonMark\Exception\CommonMarkException;

class AtasSsg
{
    protected string $projRoot;
    public PageMeta $pageMeta;
    public \stdClass $config;

    public Markdown $markdown;

    public PostsCache $postCache;

    public Layout $layout;

    public function __construct($projRoot)
    {
        if (file_exists($projRoot . "/config.json")) {
            $this->config = json_decode(file_get_contents($projRoot . "/config.json"));
        } else die("Config file not found");

        $this->projRoot = $projRoot;

        $this->pageMeta = new PageMeta();
        $this->pageMeta->desc = $this->config->site_desc;
        $this->pageMeta->og_image = $this->config->default_opengraph_image;

        $this->markdown = new Markdown($this->getCurrentHostname());

        $this->postCache = new PostsCache($this->projRoot . "/tmp/posts.json", $this->projRoot . "/posts/",
            $this->markdown);

        $this->layout = new Layout($this);
    }

    /**
     * Get a markdown file by its path, by converting that to html
     * @param $path
     * @return ConvertedMarkdown
     * @throws CommonMarkException
     */
    function getMarkdown($path): ConvertedMarkdown
    {
        if (!file_exists($path)) {
            $this->exit_with_not_found();
        }

        return $this->markdown->convert($path);
    }

    /**
     * Show not found page
     * @return void
     */
    #[NoReturn] function exit_with_not_found(): void
    {
        header('HTTP/1.0 404 Not Found');
        include_once '404.php';
        exit;
    }

    /**
     * Are we running like a PHP site or if the build pipeline running this?
     * @return bool
     */
    function isBuildRunning(): bool
    {
        return file_exists($this->projRoot . "/build.lock");
    }

    /**
     * Gets the current full hostname with protocol
     * @return string
     */
    function getCurrentHostname(): string
    {
        global $config;

        // Check if HTTPS or HTTP is being used
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";

        $host = $this->isBuildRunning() ? $config->prod_hostname : $_SERVER['HTTP_HOST'] ?? "localhost";
        $port = $_SERVER['SERVER_PORT'] ?? "80";

        // Depending on whether the port is standard for the protocol, include it in the URL
        if (($protocol === 'http' && $port == 80) || ($protocol === 'https' && $port == 443)) {
            // Standard ports for HTTP and HTTPS, respectively. No need to include the port in the URL.
            $currentHost = "{$host}";
        } else {
            // Non-standard port, include it in the URL.
            $currentHost = "{$host}:{$port}";
        }

        return $currentHost;
    }

    /**
     * Gets the current full hostname with protocol
     * @return string
     */
    function getCurrentHostnameWithProtocol(): string
    {
        // Check if HTTPS or HTTP is being used
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";

        return "{$protocol}://" . $this->getCurrentHostname();
    }

    /**
     * Gets the current full URL
     * @return string
     */
    function getCurrentFullUrl(): string
    {
        return $this->getCurrentHostnameWithProtocol() . $_SERVER['REQUEST_URI'];
    }
}
