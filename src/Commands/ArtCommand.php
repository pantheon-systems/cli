<?php

namespace Pantheon\Terminus\Commands;

use Pantheon\Terminus\Exceptions\TerminusNotFoundException;
use Pantheon\Terminus\Helpers\LocalMachineHelper;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

/**
 * Class ArtCommand
 * @package Pantheon\Terminus\Commands
 */
class ArtCommand extends TerminusCommand
{
    use StructuredListTrait;

    /**
     * @var array
     */
    protected $available_art = [
        'druplicon' => [
            'name' => 'druplicon',
            'description' => 'The mascot of Drupal'
        ],
        'fist' => [
            'name' => 'fist',
            'description' => 'The fist of Zeus',
        ],
        'hello' => [
            'name' => 'hello',
            'description' => 'A welcome from Terminus',
        ],
        'rocket' => [
            'name' => 'rocket',
            'description' => 'A rocket ship',
        ],
        'unicorn' => [
            'name' => 'unicorn',
            'description' => 'A wonderful unicorn',
        ],
        'wordpress' => [
            'name' => 'wordpress',
            'description' => 'The WordPress logo',
        ],
    ];

    /**
     * Displays Pantheon ASCII artwork.
     *
     * @command art
     *
     * @param string $name Artwork name
     *
     * @usage <artwork> Displays the <artwork> artwork.
     */
    public function art($name = 'random')
    {
        if ($name == 'random') {
            $name = $this->randomArtName()['name'];
        }
        return $this->retrieveArt($name);
    }

    /**
     * Lists available Pantheon ASCII artwork.
     *
     * @command art:list
     *
     * @field-labels
     *     name: Name
     *     description: Description
     *
     * @default-fields name,description
     *
     * @usage Displays the list of available artwork.
     *
     * @return RowsOfFields
     */
    public function listArt()
    {
        return new RowsOfFields($this->available_art);
    }

    /**
     * Set the art filename.
     *
     * @param string $name Name of artwork to get a filename for
     * @return string
     */
    protected function getFilename($name)
    {
        return $this->config->get('assets_dir') . "/$name.txt";
    }

    /**
     * Feeling lucky? Get a random artwork.
     *
     * @return string
     */
    protected function randomArtName()
    {
        return $this->available_art[array_rand($this->available_art, 1)];
    }

    /**
     * Retrieve the contents of an art file.
     *
     * @param $name
     * @return string
     * @throws TerminusNotFoundException
     */
    protected function retrieveArt($name)
    {
        $filename = $this->getFilename($name);
        $local_machine_helper = $this->getContainer()->get(LocalMachineHelper::class);
        if (!$local_machine_helper->getFilesystem()->exists($filename)) {
            throw new TerminusNotFoundException(
                'There is no source for the requested "{name}" artwork.',
                compact('name')
            );
        }
        return base64_decode($local_machine_helper->readFile($filename));
    }
}
