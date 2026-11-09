# Absence: adattamento italiano per un contesto lavorativo

Ramo locale basato sul tag ufficiale `v1.0.6`, commit
`db4e1d068ccbbabe240b8117a30dcadb463e147a`. La versione `1.0.9` di questo ramo
identifica una build personalizzata, non una release ufficiale Nextcloud.

- Interfaccia senza emoticon; icona di navigazione a calendario monocromatico.
- Campo sostituto disattivato per impostazione predefinita; configurabile dalle impostazioni generali, ma bloccato in modalità privata.
- Modello con 3 tipologie principali in `resources/italian-leave-types.json`.
- Politica di riporto con tre scelte esplicite, compatibili con il modulo impostazioni Nextcloud 35.
- Catalogo italiano: 366 voci, inclusi plurali, notifiche ed errori applicativi.
- Terminologia: ferie, permessi, richieste di assenza, responsabile e ufficio del personale.
- Date e numeri dell'interfaccia seguono la lingua/locale impostata in Nextcloud.
- CSV italiano: UTF-8 con BOM, separatore `;`, virgola decimale, date `gg/mm/aaaa`,
  intestazioni e stati tradotti. Resta attiva la protezione dalle formule nei fogli di calcolo.
- Nomi dei paesi localizzati; festività italiane proposte dal locale dell'organizzazione,
  mantenendo prioritarie le preferenze esplicite dell'utente.
- I nomi delle tipologie personalizzate non vengono riscritti nel database.
- Una migrazione rende vuoto il solo valore predefinito SQL dell'icona, per evitare
  differenze di schema con i metadati MariaDB. Le icone già memorizzate restano intatte.

Le quantità restano espresse in giorni o frazioni di giorno. Il modello non introduce contatori ROL/ex festività in ore; i tipi aggiuntivi non consumano il saldo ferie.

La patch non definisce un CCNL, non modifica il monte ferie predefinito di 25 giorni
e non cambia regole di approvazione, ruoli o contabilizzazione. Tali impostazioni
devono corrispondere alle regole effettive dell'organizzazione.

## Verifiche eseguite

Build con Node 24 e `npm ci --legacy-peer-deps --ignore-scripts`, controllo dei
segnaposto di traduzione, sintassi PHP e schema Nextcloud. Su una copia isolata di
Nextcloud 35.0.2 con MariaDB 11.8 e Redis: autenticazione dipendente/personale,
richiesta e approvazione ferie, saldo 25 → 24, rifiuto dell'autoapprovazione,
protezione dei report, errori italiani e CSV con date/intestazioni/stati italiani. Sono stati verificati anche sostituto omesso, valido, rimosso e non valido, oltre al salvataggio delle tre politiche di riporto.

## Manutenzione

La cartella `js/` contiene gli asset compilati. Gli aggiornamenti ufficiali di
Absence possono sostituire questa personalizzazione: conservare la patch Git,
verificare le differenze e ripetere le prove su una copia prima di aggiornare.
Il repository contiene solo codice e traduzioni: credenziali, dati dell'azienda,
backup e configurazioni dell'istanza devono restare fuori da Git.

## Configurazione e permessi

Da **Assenze → Configurazione**, HR può aggiungere, rinominare, attivare o
 disattivare le tipologie e scegliere approvazione, richiesta da parte del
 dipendente, saldo in giorni e motivazione obbligatoria. Le tipologie disattivate
 restano disponibili nello storico. Le impostazioni generali sono riservate agli
 amministratori Nextcloud.

La configurazione iniziale comprende **Ferie, Permesso, Malattia**. Tutte richiedono
 approvazione; il solo saldo Ferie è attivo inizialmente. Gli importi e i diritti
 contrattuali restano da configurare dall'organizzazione.

La modalità privata è attiva per impostazione predefinita: dipendenti solo sulle
 proprie assenze; responsabili sui propri collaboratori; HR su tutta l'organizzazione.
 I controlli si applicano alle API, ai conteggi di copertura e alle richieste dirette,
 non solo ai menu. La vecchia opzione di calendario “neutrale” nascondeva solo il
 tipo di assenza; la modalità privata filtra anche identità e date dei colleghi.
 Il calendario condiviso e le notifiche ai sostituti sono bloccati in modalità privata.
 La sincronizzazione personale non scrive in calendari già condivisi o pubblicati.

Verifica su copia isolata: due colleghi con lo stesso responsabile, richieste
 dirette, calendario, copertura, statistiche, saldi, esportazioni e modifica delle
 tipologie. Test unitari: 45 test, 182 asserzioni, superati su PHP 8.5.12.
