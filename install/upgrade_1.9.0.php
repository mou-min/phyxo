<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\Repository\UpgradeRepository;
use Phyxo\Functions\Upgrade;

$release_from = '1.9.0';
$first_id = 148;
$last_id = 148;

if (!defined('PHPWG_ROOT_PATH')) {
    define('PHPWG_ROOT_PATH', __DIR__ . '/../');
}

$env_file = __DIR__ . '/../.env';
if (!file_exists($env_file)) {
    $env_file_content = 'APP_ENV=prod' . "\n";
    $env_file_content .= 'APP_SECRET=' . hash('sha256', openssl_random_pseudo_bytes(50)) . "\n";
    file_put_contents($env_file, $env_file_content);
    echo 'Env file ".env" created.' . "<br/>\n";
}

// retrieve already applied upgrades
$result = (new UpgradeRepository($conn))->findAll();
$applied = $conn->result2array($result, null, 'id');

// retrieve existing upgrades
$existing = Upgrade::get_available_upgrade_ids();

// which upgrades need to be applied?
$to_apply = array_diff($existing, $applied);
$inserts = [];
foreach ($to_apply as $upgrade_id) {
    if ($upgrade_id >= $first_id) { // TODO change on each release
        break;
    }

    $inserts[] = [
        'id' => $upgrade_id,
        'applied' => 'now()',
        'description' => sprintf('[migration from %s to %s] not applied', $release_from, PHPWG_VERSION)
    ];
}

if (!empty($inserts)) {
    (new UpgradeRepository($conn))->massInserts(array_keys($inserts[0]), $inserts);
}

// +-----------------------------------------------------------------------+
// |                          Perform upgrades                             |
// +-----------------------------------------------------------------------+

ob_start();
echo '<pre>';

for ($upgrade_id = $first_id; $upgrade_id <= $last_id; $upgrade_id++) {
    $upgrade_file = __DIR__ . '/db/' . $upgrade_id . '-database.php';
    if (!is_readable($upgrade_file)) {
        continue;
    }

    // maybe the upgrade task has already been applied in a previous and
    // incomplete upgrade
    if (in_array($upgrade_id, $applied)) {
        continue;
    }

    unset($upgrade_description);

    echo "\n\n";
    echo '=== upgrade ' . $upgrade_id . "\n";

    // include & execute upgrade script. Each upgrade script must contain
    // $upgrade_description variable which describe briefly what the upgrade
    // script does.
    include($upgrade_file);

    // notify upgrade (TODO change on each release)
    (new UpgradeRepository($conn))->addUpgrade("$upgrade_id", '[migration from ' . $release_from . ' to ' . PHPWG_VERSION . '] ' . $upgrade_description);
}

echo '</pre>';
ob_end_clean();
