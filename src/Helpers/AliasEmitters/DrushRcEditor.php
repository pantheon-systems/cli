<?php

namespace Pantheon\Terminus\Helpers\AliasEmitters;

use Symfony\Component\Yaml\Yaml;

class DrushRcEditor
{
    protected $dir;

    /**
     * DrushRcEditor constructor
     *
     * @param string $dir
     */
    public function __construct($dir)
    {
        $this->dir = $dir;
    }

    /**
     * Load the drushrc.php file and return its parsed contents.
     *
     * @return string[]
     */
    public function getDrushConfig()
    {
        $drushRCPath = $this->getDrushRCPath();
        // Load the drushrc.php file
        if (file_exists($drushRCPath)) {
            $drushRCContents = file_get_contents($drushRCPath);
        } else {
            $drushRCContents = '<?php' . "\n";
        }
        $drushRCContents = explode("\n", $drushRCContents);
        return $drushRCContents;
    }

    /**
     * Return the path to the drushrc.php file.
     *
     * @return string
     */
    public function getDrushRCPath()
    {
        return $this->dir . "/drushrc.php";
    }

    /**
     * Write a modified drushrc.php file back to disk.
     */
    public function writeDrushConfig($drushRCText)
    {
        $drushRCPath = $this->getDrushRCPath();
        return file_put_contents($drushRCPath, $drushRCText);
    }
}
