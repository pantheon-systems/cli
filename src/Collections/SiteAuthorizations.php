<?php

namespace Pantheon\Terminus\Collections;

class SiteAuthorizations extends TerminusCollection
{
  /**
   * @var Site
   */
    public $site;
  /**
   * @var string
   */
    protected $collected_class = 'Pantheon\Terminus\Models\SiteAuthorization';

  /**
   * Object constructor
   *
   * @param array $options Options to set as $this->key
   */
    public function __construct($options = [])
    {
        parent::__construct($options);
        $this->site = $options['site'];
        $this->url = "sites/{$this->site->id}/authorizations";
    }

  /**
   * Adds a model to this collection
   *
   * @param object $model_data Data to feed into attributes of new model
   * @param array  $options    Data to make properties of the new model
   * @return SiteAuthorization
   */
    
    public function add($model_data, array $options = [])
    {
        $options = array_merge(
            ['id' => $model_data->id, 'collection' => $this,],
            $options
        );
        $model = $this->getContainer()->get($this->collected_class, [$model_data, $options]);
        $model_id = $model_data->id;
        if (property_exists($model_data, 'environment')) {
            $model_id .= '_' . $model_data->environment;
        }

        $this->models[$model_id] = $model;
        return $model;
    }
}
