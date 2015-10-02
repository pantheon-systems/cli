<?php
/**
 * @file
 * Contains Terminus\Outputters\BashFormatter
 */


namespace Terminus\Outputters;


/**
 * Class BashFormatter
 * @package Terminus\Outputters
 */
class BashFormatter implements OutputFormatterInterface {
  const FIELD_SEPARATOR = ' ';
  const ROW_SEPARATOR = "\n";
  const VALUE_SEPARATOR = ',';

  /**
   * Formats a single scalar value with an optional human label.
   *
   * @param mixed $value
   *  The scalar value to format
   * @param string $human_label
   *  The human readable label for the value
   * @return string
   */
  public function formatValue($value, $human_label = '') {
    $value = BashFormatter::flattenValue($value);
    return $value . BashFormatter::ROW_SEPARATOR;
  }

  /**
   * Format a single record or object
   *
   * @param array|object $record
   *   A key/value array or object
   * @param array $human_labels
   *   A key/value array mapping the keys in the record to human labels
   * @return string
   */
  public function formatRecord($record, $human_labels = array()) {
    $out = '';
    foreach ((array)$record as $key => $value) {
      $value = BashFormatter::flattenValue($value);
      $out .= $key . BashFormatter::FIELD_SEPARATOR . $value . BashFormatter::ROW_SEPARATOR;
    }
    return $out;
  }

  /**
   * Format a list of scalar values
   *
   * @param array $values
   *  The values to format
   * @param string $human_label
   *  A human name for the entire list. If each value needs a separate label then
   *  formatRecord should be used.
   * @return string
   */
  public function formatValueList($values, $human_label = '') {
    $out = '';
    foreach ($values as $value) {
      $out .= $this->formatValue($value);
    }
    return $out;
  }

  /**
   * Format a list of records of the same type.
   *
   * @param array $records
   *  A list of arrays or objects.
   * @param array $human_labels
   *  An array that maps the record keys to human names.
   * @return string
   */
  public function formatRecordList($records, $human_labels = array()) {
    $out = '';
    foreach ($records as $record) {
      foreach ((array)$record as $value) {
        $out .= BashFormatter::flattenValue($value);
        $out .= BashFormatter::FIELD_SEPARATOR;
      }
      // Remove the trailing separator.
      $out = substr($out, 0, strlen($out)-(strlen(BashFormatter::FIELD_SEPARATOR)));
      $out .= BashFormatter::ROW_SEPARATOR;
    }
    return $out;
  }

  /**
   * Format any kind of value as a raw dump.
   *
   * @param $object
   * @return string
   */
  public function formatDump($object) {
    return print_r($object, true);
  }

  /**
   * Format a message to the user.
   *
   * @param string $level
   * @param string $message
   * @param array $context
   * @return string
   */
  public function formatMessage($level, $message, $context = array()) {
    $object = array(
      'level' => $level,
      'message' => $message,
      'context' => $context
    );
    return $this->formatRecord($object);
  }

  /**
   * Flatten a value for display
   * @param $value
   */
  private static function flattenValue($value) {
    if (is_array($value) || is_object($value)) {
      $value = join(BashFormatter::VALUE_SEPARATOR, (array)$value);
    }
    return $value;
  }


}