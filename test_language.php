<?php

echo "=== Test direct de la base de données ===\n";

// Test direct avec SQLite
$pdo = new PDO('sqlite:/workspace/oragon/var/data_dev.db');

echo "\n1. Structure de la table languages:\n";
$result = $pdo->query("PRAGMA table_info(languages)");
foreach ($result as $row) {
    echo sprintf("- %s: %s\n", $row['name'], $row['type']);
}

echo "\n2. Toutes les langues dans la base:\n";
$result = $pdo->query("SELECT * FROM languages ORDER BY sort_order");
foreach ($result as $row) {
    echo sprintf("- ID:%s | Code:%s | Name:%s | NativeName:%s | Active:%s | Default:%s | Order:%s\n", 
        $row['id'], $row['code'], $row['name'], $row['native_name'], 
        $row['is_active'] ? 'Oui' : 'Non', 
        $row['is_default'] ? 'Oui' : 'Non',
        $row['sort_order']
    );
}

echo "\n3. Langues actives:\n";
$result = $pdo->query("SELECT * FROM languages WHERE is_active = 1 ORDER BY sort_order");
foreach ($result as $row) {
    echo sprintf("- %s (%s) - %s [Active: %s, Default: %s]\n", 
        $row['code'], $row['name'], $row['native_name'],
        $row['is_active'] ? 'Oui' : 'Non',
        $row['is_default'] ? 'Oui' : 'Non'
    );
}

echo "\n4. Test spécifique pour l'anglais:\n";
$stmt = $pdo->prepare("SELECT * FROM languages WHERE code = ? AND is_active = 1");
$stmt->execute(['en']);
$englishRow = $stmt->fetch();
if ($englishRow) {
    echo sprintf("✅ Anglais trouvé: %s (%s) - %s [Active: %s]\n", 
        $englishRow['code'], $englishRow['name'], $englishRow['native_name'],
        $englishRow['is_active'] ? 'Oui' : 'Non'
    );
} else {
    echo "❌ Anglais NOT FOUND!\n";
}

echo "\n=== Fin du test ===";
