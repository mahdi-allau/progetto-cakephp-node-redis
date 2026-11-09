<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class DeviceConfigHistory extends Entity
{
    protected array $_accessible = [
        'device_id' => true,
        'target_rpm' => true,
        'kp' => true,
        'ki' => true,
        'kd' => true,
        'pid_samp' => true,
        'fan_pid_enabled' => true,
        'temp_threshold' => true,
        'humidity_threshold' => true,
        'created' => true,
    ];
}