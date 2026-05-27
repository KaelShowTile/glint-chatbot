<?php
require 'vendor/autoload.php';
$llm = new App\Services\LlmService();
try {
    $vector = $llm->embed('test');
    echo "Vector size: " . count($vector) . "\n";
    $qdrant = new App\Services\VectorService();
    $res = $qdrant->search($vector, 1);
    print_r($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
