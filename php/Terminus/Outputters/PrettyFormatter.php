<?php
/**
 * @file
 * Contains Terminus\Outputters\PrettyFormatter
 */


namespace Terminus\Outputters;


class PrettyFormatter implements OutputFormatterInterface {

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
    $value = PrettyFormatter::flattenValue($value);
    return $human_label ? "$human_label: $value" . PHP_EOL : $value;
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
    // No output for empty records. This should be handled by the logger with a friendly message.
    if (empty($record)) {
      return '';
    }

    // Normalize the keys
    $rows = array();

    // Get the headers
    $record = (array)$record;
    foreach ((array)$record as $key => $value) {
      $label = self::getHumanLabel($key, $human_labels);
      $rows[] = array($label, $value);
    }

    return $this->formatTable($rows, array('Key', 'Value'));
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
    $this->formatValue(implode(', ', $values), $human_label);
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
    // No output for empty records. This should be handled by the logger with a friendly message.
    if (empty($records)) {
      return '';
    }

    // TODO: Implement formatRecordList() method.
    // Normalize the keys
    $keys = array();
    $header = array();
    // Get all of the keys from all of the records.
    foreach ($records as $record) {
      foreach ($record as $key => $value) {
        if (!in_array($key, $keys)) {
          $keys[] = $key;
        }
      }
    }
    // Add missing values
    foreach ($records as $i => $record) {
      $new = array();
      $record = (array)$record;
      foreach ($keys as $key) {
        $new[$key] = isset($record[$key]) ? PrettyFormatter::flattenValue($record[$key], $human_labels) : '';
      }
      $records[$i] = $new;
    }
    // Get the headers
    foreach ($keys as $key) {
      $header[] = self::getHumanLabel($key, $human_labels);
    }

    return $this->formatTable($records, $header);
  }

  /**
   * @param $data
   * @param $headers
   */
  private function formatTable($data, $headers = null) {
    $table = new \cli\Table();

    if ($headers) {
      $table->setHeaders($headers);
    }
    foreach ($data as $row_data) {
      $row = array();
      foreach ((array)$row_data as $key => $value) {
        $value = PrettyFormatter::flattenValue($value);
        $row[] = $value;
      }
      $table->addRow($row);
    }

    // @TODO: This does not test well. PHPUnit uses output buffering.
    ob_start();
    $table->display();
    $out = ob_get_contents();
    ob_end_clean();
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
    return '['. strtoupper($level) . '] ' . $this->interpolate($message, $context) . PHP_EOL;
  }

  /**
   * Get the human name for a key if available.
   *
   * @param string $key
   * @param array $human_labels
   * @return string
   */
  private static function getHumanLabel($key, $human_labels) {
    return isset($human_labels[$key]) ? $human_labels[$key] : ucwords(strtr($key, '_', ' '));
  }

  /**
   * Flatten a value for display
   *
   * @param mixed $value
   * @return string
   */
  private static function flattenValue($value, $human_labels = array()) {
    if (is_scalar($value)) {
      return $value;
    }

    // Merge an array or object down. Doesn't look great past 2 levels of depth.
    $is_assoc = is_array($value) && (bool)count(array_filter(array_keys($value), 'is_string'));
    if ($is_assoc || is_object($value)) {
      foreach ($value as $key => $val) {
        $value[$key] = PrettyFormatter::getHumanLabel($key, $human_labels) . ': ' . PrettyFormatter::flattenValue($val, $human_labels);
      }
    }
    $value = join(', ', (array)$value);
    return $value;
  }


  /**
   * Interpolates context variables per the PSR spec.
   *
   * @param string $message The message containing replacements in the form {key}
   * @param array $context The array containing the values to be substituted.
   * @return string
   */
  private function interpolate($message, $context) {
    // build a replacement array with braces around the context keys
    $replace = array();
    foreach ($context as $key => $val) {
      $replace['{' . $key . '}'] = $val;
    }

    // interpolate replacement values into the message and return
    return strtr($message, $replace);
  }

}