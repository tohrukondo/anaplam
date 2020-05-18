<?php

require dirname(dirname(__FILE__)) . '/vendor/autoload.php';

$opts = "c::d:h";
$options = getopt($opts);

if ((isset($options['c']) && isset($options['d'])) || isset($options['h'])) {
    echo "usage: \n";
    echo " create: apikey.php [-c |-c='api-key']\n";
    echo " delete: apikey.php -d='api-key'\n";
    exit;
}

$apiKeyModel = new Anaplam\Model\ApiKeyModel();

if (empty($options) || isset($options['l'])) {
    // show all api keys
    $apiKeys = $apiKeyModel->getAll();
    if (!empty($apiKeys)) {
        foreach ($apiKeys as $apiKey) {
            echo 'api-key ' . $apiKey['id'] . ' "' . $apiKey['api_key'] . '" created at ' . date(DATE_ISO8601, strtotime($apiKey['created_at']))."\n";
        }

    } else {
        echo 'api key is not created.';
    }
    exit;
}

if (isset($options['c'])) {
    /* create */
    $api_key = md5(uniqid(mt_rand(), true));
    if (!empty($options['c'])) {
        $api_key = $options['c'];
    }
    if ($apiKeyModel->exist($api_key)) {
        echo 'already exists api key:' . $api_key . "\n";
        exit;
    }
    if ($apiKeyModel->create($api_key)) {
        echo 'create new api key : ' . $api_key . "\n";
    } else {
        echo 'create failed api key';
    }
    exit;
} elseif (isset($options['d'])) {
    /* delete */
    if (empty($options['d'])) {
        echo "usage:\n delete: apikey.php -d='api-key'\n";
        exit;
    } else {
        $api_key = $options['d'];
    }
    if (!$apiKeyModel->exist($api_key)) {
        echo 'no exists api key:' . $api_key . "\n";
        exit;
    }
    if ($apiKeyModel->delete($api_key)) {
        echo 'delete api key success.' . $api_key . "\n";
    } else {
        echo 'delete api key failed.' . $api_key . "\n";
    }
    exit;
}

exit;

