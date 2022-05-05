<?php

namespace Pantheon\Terminus\Commands;

use Consolidation\OutputFormatters\StructuredData\AbstractStructuredList;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Pantheon\Terminus\Collections\TerminusCollection;
use Pantheon\Terminus\Models\TerminusModel;

/**
 * Class StructuredListTrait
 * @package Pantheon\Terminus\Commands
 */
trait StructuredListTrait
{
    /**
     * @param TerminusModel $model A model with data to extract
     * @return PropertyList A PropertyList-type object with applied filters
     */
    public function getPropertyList(TerminusModel $model)
    {
        $list = new PropertyList($model->serialize());
        $list = $this->addBooleanRenderer($list);
        $list = $this->addDatetimeRenderer($list, $model::$date_attributes);
        return $list;
    }

    /**
     * @param TerminusCollection $collection A collection of models to get the data from
     * @param array $options Elements as follow
     *        string $message Message to emit if the collection is empty.
     *        array $message_options Values to interpolate into the error message.
     *        function $sort A function to sort the data using
     * @return RowsOfFields Returns a RowsOfFields-type object with applied filters
     */
    public function getRowsOfFields(TerminusCollection $collection, array $options = [])
    {
        $data = $collection->serialize();
        var_dump($collection->getCollectedClass());
        var_dump(get_class($collection));
        ob_flush();
        $collection_name = $collection::PRETTY_NAME;
        $model_name = $collection->getCollectedClass();
        $model = new $model_name();
        $date_attributes = $model::$date_attributes;
        return $this->prepareTableFromData($data, $collection_name, $options, $date_attributes);
    }

    /**
     * @param array $data Data already serialized (i.e. not a TerminusCollection)
     * @param array $options Elements as follow
     *        string $message Message to emit if the collection is empty.
     *        array $message_options Values to interpolate into the error message.
     *        function $sort A function to sort the data using
     * @return RowsOfFields Returns a RowsOfFields-type object with applied filters
     */
    protected function prepareTableFromData(
        array $data,
        string $collection_name,
        array $option = [],
        $date_attributes = []
    ) {
        if (count($data) === 0) {
            $message = isset($options['message'])
                ? $options['message']
                : 'You have no ' . $collection_name . '.';
            $options = isset($options['message_options']) ? $options['message_options'] : [];
            $this->log()->warning($message, $options);
        }

        if (!empty($options['sort'])) {
            usort($data, $options['sort']);
        }

        $table = new RowsOfFields($data);
        $table = $this->addBooleanRenderer($table);
        $table = $this->addDatetimeRenderer($table, $date_attributes);
        return $table;
    }

    /**
     * @param array $data Data already serialized (i.e. not a TerminusCollection)
     * @param string $items_name Name of the collections in the data
     * @param array $options Elements as follow
     *        string $message Message to emit if the collection is empty.
     *        array $message_options Values to interpolate into the error message.
     *        function $sort A function to sort the data using
     * @return RowsOfFields Returns a RowsOfFields-type object with applied filters
     */
    public function getRowsOfFieldsFromSerializedData(array $data, string $items_name, array $options = [])
    {
        return $this->prepareTableFromData($data, $items_name, $options);
    }

    /**
     * Adds a renderer function to the RowsOfFields object to format booleans into strings
     *
     * @param AbstractStructuredList $table
     * @return AbstractStructuredList
     */
    private function addBooleanRenderer(AbstractStructuredList $list)
    {
        $list->addRendererFunction(
            function ($key, $cell_data) {
                if ($cell_data === true) {
                    return 'true';
                } elseif ($cell_data === false) {
                    return 'false';
                }
                return $cell_data;
            }
        );
        return $list;
    }

    /**
     * Adds a renderer function to the structured list to format datetimes when rendering
     *
     * @param AbstractStructuredList $table
     * @param array $date_attributes
     * @return RowsOfFields
     */
    private function addDatetimeRenderer(AbstractStructuredList $list, array $date_attributes)
    {
        $config = $this->getConfig();

        $list->addRendererFunction(
            function ($key, $cell_data) use ($config, $date_attributes) {
                if (!is_numeric($key) && in_array($key, $date_attributes)) {
                    return $config->formatDatetime($cell_data);
                }
                return $cell_data;
            }
        );
        return $list;
    }
}
