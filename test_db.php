<?php
$db = new PDO(
    "pgsql:host=dpg-d19h6lfgi27c73crpsrg-a;dbname=stripe_test_ase0;sslmode=require",
    "stripe_test_ase0_user",
    "0zMaW0fLMN9N8XCgHJqQZ7gevMesVeCZ",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$db->query("INSERT INTO stripe_webhooks(event_id) VALUES ('test_".time()."')");
echo "Operazione riuscita!";
?>
