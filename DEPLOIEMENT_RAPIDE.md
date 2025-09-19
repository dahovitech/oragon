# 🚀 Déploiement Rapide d'Oragon sur HestiaCP

## ⚠️ Prérequis OBLIGATOIRE

**Avant tout, créez le domaine dans HestiaCP :**

1. Connectez-vous au panel : `https://46.202.129.197:8083`
2. **Web** → **Add Web Domain**  
3. Domaine : `oragon.achatrembourse.online`
4. Cocher **SSL Support** (Let's Encrypt)
5. Cliquer sur **Add**

---

## 🎯 Commande de Déploiement en Une Ligne

Connectez-vous à votre serveur :

```bash
ssh mrjoker@46.202.129.197
```

Puis exécutez cette commande unique :

```bash
wget https://raw.githubusercontent.com/dahovitech/oragon/main/deploy_oragon_hestia.sh && chmod +x deploy_oragon_hestia.sh && ./deploy_oragon_hestia.sh
```

**C'est tout !** ✨

---

## Alternative : Commandes Séparées

Si vous préférez exécuter étape par étape :

```bash
# 1. Télécharger le script HestiaCP
wget https://raw.githubusercontent.com/dahovitech/oragon/main/deploy_oragon_hestia.sh

# 2. Rendre exécutable
chmod +x deploy_oragon_hestia.sh

# 3. Exécuter le déploiement
./deploy_oragon_hestia.sh
```

---

## 🏗️ Que fait le script automatiquement ?

✅ **Vérification environnement HestiaCP** (utilisateur, domaine)  
✅ **Installation de Composer** si nécessaire  
✅ **Clonage du projet** dans le bon répertoire  
✅ **Adaptation structure fichiers** pour HestiaCP  
✅ **Configuration .htaccess** Symfony  
✅ **Installation dépendances** PHP (mode dev)  
✅ **Configuration environnement** de développement  
✅ **Initialisation base de données** SQLite  
✅ **Permissions optimisées** HestiaCP  

---

## 🌐 Résultat Attendu

Après exécution, votre site sera accessible sur :  
**🌐 http://oragon.achatrembourse.online/**

**🔧 Profiler de debug :** http://oragon.achatrembourse.online/_profiler

---

## 🏃‍♂️ Structure HestiaCP

Le script adapte automatiquement Symfony pour HestiaCP :

```
/home/mrjoker/web/oragon.achatrembourse.online/public_html/
├── index.php          ← Point d'entrée Symfony
├── .htaccess         ← Configuration rewrite  
├── src/              ← Code source
├── var/              ← Cache et logs
├── vendor/           ← Dépendances  
└── public/           ← Assets originaux
```

---

## 🆘 En cas de problème

**Guide complet :** `GUIDE_DEPLOIEMENT_HESTIA.md`

**Logs à vérifier :**
```bash
# Logs du domaine
tail -f /home/mrjoker/web/oragon.achatrembourse.online/logs/error.log

# Logs Symfony
tail -f /home/mrjoker/web/oragon.achatrembourse.online/public_html/var/log/dev.log
```

**Panel HestiaCP :** https://46.202.129.197:8083

---

**⏱️ Temps estimé : 1-2 minutes** (après création du domaine)
