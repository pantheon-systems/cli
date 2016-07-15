<?php

namespace Terminus\Models;

//@TODO Create archives collection and change backups accordingly
class Backup extends NewModel {
  /**
   * @var Environment
   */
  public $environment;

  /**
   * Object constructor
   *
   * @param object $attributes Attributes of this model
   * @param array $options    Options to set as $this->key
   * @return Backup
   */
  public function __construct($attributes = null, array $options = []) {
    parent::__construct($attributes, $options);
    $this->environment = $options['collection']->environment;
  }

  /**
   * Determines whether the backup has been completed or not
   *
   * @return bool True if backup is completed.
   */
  public function backupIsFinished() {
    $is_finished = (
      ($this->get('size') != 0)
      && (
        ($this->get('finish_time') != null)
        || ($this->get('timestamp') != null)
      )
    );
    return $is_finished;
  }

  /**
   * Returns the bucket name for this backup
   *
   * @return string
   */
  public function getBucket() {
    $bucket = str_replace('_' . $this->getElement(), '', $this->id);
    return $bucket;
  }

  /**
   * Returns the date the backup was completed
   *
   * @return string Timestamp completion time or "Pending"
   */
  public function getDate() {
    if ($this->get('finish_time') != null) {
      $datetime = $this->get('finish_time');
    } elseif ($this->get('timestamp') != null) {
      $datetime = $this->get('timestamp');
    } else {
      return 'Pending';
    }
    $date = date(TERMINUS_DATE_FORMAT, $datetime);
    return $date;
  }

  /**
   * Returns the element type of the backup
   *
   * @return string code, database, files, or null
   */
  public function getElement() {
    if ($this->get('filename') == null) {
      return null;
    }
    preg_match(
      '~(?:.*_|^)(.*)\.(?:tar|sql).gz$~',
      $this->get('filename'),
      $type_match
    );
    if (isset($type_match[1])) {
      $type = $type_match[1];
    } else {
      return null;
    }
    return $type;
  }

  /**
   * Returns the type of initiator of the backup
   *
   * @return string Either "manual" or "automated"
   */
  public function getInitiator() {
    $initiator = 'manual';
    preg_match("/.*_(.*)/", $this->get('folder'), $automation_match);
    if (isset($automation_match[1]) && ($automation_match[1] == 'automated')) {
      $initiator = 'automated';
    }
    return $initiator;
  }

  /**
   * Returns the size of the backup in MB
   *
   * @return string A number (int or float) followed by 'MB'.
   */
  public function getSizeInMb() {
    $size_string = '0';
    if ($this->get('size') != null) {
      $size = $this->get('size') / 1048576;
      if ($size > 0.1) {
        $size_string = sprintf('%.1fMB', $size);
      } elseif ($size > 0) {
        $size_string = '0.1MB';
      }
    }
    return $size_string;
  }

  /**
   * Gets the URL of a backup
   *
   * @return string
   */
  public function getUrl() {
    $path     = sprintf(
      'sites/%s/environments/%s/backups/catalog/%s/%s/s3token',
      $this->environment->site->id,
      $this->environment->id,
      $this->getBucket(),
      $this->getElement()
    );
    $options  = ['method' => 'post', 'form_params' => ['method' => 'get',],];
    $response = $this->request->request($path, $options);
    return $response['data']->url;
  }

}
