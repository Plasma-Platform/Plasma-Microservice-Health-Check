<?php
/**
 * @package templatemonster\healthchecks
 */

namespace templatemonster\healthchecks\models;

use yii\base\Model;

class HealthCheck extends Model
{
    public $name;
    public $passed;
}
