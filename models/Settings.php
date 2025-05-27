<?php namespace Allway\Chat\Models;

use Model;
use October\Rain\Database\Traits\Validation;
use System\Behaviors\SettingsModel;

class Settings extends Model
{
    use Validation;

    public $implement = [SettingsModel::class];

    public $settingsCode = 'allway_chat_settings';
    public $settingsFields = 'fields.yaml';

    public $rules = [
    ];
}
