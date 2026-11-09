# Absence: adattamento italiano per un contesto lavorativo

Ramo locale basato sul tag ufficiale `v1.0.6`, commit
`db4e1d068ccbbabe240b8117a30dcadb463e147a`. La versione `1.0.7` di questo ramo
identifica una build personalizzata, non una release ufficiale Nextcloud.

- Catalogo italiano: 332 voci, inclusi plurali, notifiche ed errori applicativi.
- Terminologia: ferie, permessi, richieste di assenza, responsabile e ufficio del personale.
- Date e numeri dell'interfaccia seguono la lingua/locale impostata in Nextcloud.
- CSV italiano: UTF-8 con BOM, separatore `;`, virgola decimale, date `gg/mm/aaaa`,
  intestazioni e stati tradotti. Resta attiva la protezione dalle formule nei fogli di calcolo.
- Nomi dei paesi localizzati; festività italiane proposte dal locale dell'organizzazione,
  mantenendo prioritarie le preferenze esplicite dell'utente.
- I nomi delle tipologie personalizzate non vengono riscritti nel database.
- Una migrazione rende vuoto il solo valore predefinito SQL dell'icona, per evitare
  differenze di schema con i metadati MariaDB. Le icone già memorizzate restano intatte.

La patch non definisce un CCNL, non modifica il monte ferie predefinito di 25 giorni
e non cambia regole di approvazione, ruoli o contabilizzazione. Tali impostazioni
devono corrispondere alle regole effettive dell'organizzazione.

## Verifiche eseguite

Build con Node 24 e `npm ci --legacy-peer-deps --ignore-scripts`, controllo dei
segnaposto di traduzione, sintassi PHP e schema Nextcloud. Su una copia isolata di
Nextcloud 35.0.2 con MariaDB 11.8 e Redis: autenticazione dipendente/personale,
richiesta e approvazione ferie, saldo 25 → 24, rifiuto dell'autoapprovazione,
protezione dei report, errori italiani e CSV con date/intestazioni/stati italiani.

## Manutenzione

La cartella `js/` contiene gli asset compilati. Gli aggiornamenti ufficiali di
Absence possono sostituire questa personalizzazione: conservare la patch Git,
verificare le differenze e ripetere le prove su una copia prima di aggiornare.
Il repository contiene solo codice e traduzioni: credenziali, dati dell'azienda,
backup e configurazioni dell'istanza devono restare fuori da Git.
