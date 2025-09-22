#!/bin/bash

# Script de nettoyage complet du cache Symfony

echo "=== Nettoyage du cache Symfony ==="

# Supprimer le dossier var/cache
echo "Suppression du dossier var/cache..."
rm -rf var/cache/*

# Supprimer les fichiers de log si nécessaire
echo "Suppression des logs..."
rm -rf var/log/*

# Recréer les dossiers nécessaires
echo "Recréation des dossiers..."
mkdir -p var/cache
mkdir -p var/log

# Définir les permissions appropriées
echo "Configuration des permissions..."
chmod -R 777 var/cache
chmod -R 777 var/log

echo "=== Cache nettoyé avec succès ==="
echo "Vous pouvez maintenant exécuter votre application Symfony."
echo "N'oubliez pas d'exécuter 'php bin/console cache:warmup' si nécessaire."
