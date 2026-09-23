🔮 Zodiac Signs 1.0.5 per phpBB 3.3.x
Segno zodiacale per gli iscritti (scelto in automatico dalla data di nascita, l'utente ha sempre l'ultima parola), dodici icone animate originali, oroscopo settimanale e mensile aggiornato da un cron.

![Version](https://img.shields.io/badge/version-1.0.5-105080)
![phpBB](https://img.shields.io/badge/phpBB-3.3.x-377a33)
![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-377a33)
![License](https://img.shields.io/badge/license-GPL--2.0--only-7f7f7f)

🛠️ Installazione
Carica la cartella salvocortesiano/zodiacsigns in ext/ (il percorso finale dovrà essere: ext/salvocortesiano/zodiacsigns/composer.json).
Vai in ACP > Personalizza > Gestione estensioni > Zodiac Signs e clicca su Attiva.
Vai in ACP > Estensioni > Zodiac Signs e rivedi le quattro schede di configurazione.
Opzionale: Se vuoi caricare immagini tue, la cartella images/zodiacsigns/ viene creata in automatico al primo caricamento; se il tuo server non lo permette, creala manualmente e assegnale i permessi 755.

📡 Fonti dell'oroscopo
(Trovate nella scheda "Oroscopo" > "Fonti da usare")

🆓 Gratuite (inglese): due fonti già configurate, non richiedono alcuna chiave.

🔑 API personali: usa solo le API che aggiungi tu nella sezione "Fonti dell'oroscopo", ciascuna con la sua lingua (italiano o inglese).

⚖️ API personali con riserva gratuita: il sistema interroga prima le tue API; se queste non rispondono, ripiega su quelle gratuite.
Nota: Per ogni lingua, le fonti vengono provate in ordine di priorità; se nessuna risponde, resta visibile l'ultimo testo valido. Impostando "Lingua del visitatore", chi usa il forum in italiano leggerà il testo in italiano, mentre gli altri leggeranno quello in inglese.

💡 Esempio di API a pagamento (schema tipico RapidAPI)
Indirizzo settimanale: https://nome-api.p.rapidapi.com/horoscope?sign={sign}&period=weekly
Intestazione della chiave: X-RapidAPI-Key
Chiave: <la_tua_chiave>
Altre intestazioni: X-RapidAPI-Host: nome-api.p.rapidapi.com
Percorso del testo: Quello indicato nella documentazione dell'API (es. data.text)
Salva la configurazione e premi Prova: vedrai l'inizio del testo ricevuto o l'eventuale messaggio di errore.

🩺 Check-up (dalla versione 1.0.1)
Vai in ACP > Estensioni > Zodiac Signs > Check-up. Questo strumento controlla:
Server (PHP, cURL, OpenSSL, cifratura)
Database, impostazioni e gruppi
Immagini e file di lingua
Punti di aggancio (event) degli stili attivi (inclusi quelli ereditati)
Calcolo del segno
Chiavi API e fonti (configurazione e, a richiesta, prova di rete su 1 o 12 segni mostrando codice HTTP, tempi di risposta e percorso JSON)
Testi salvati, cron e risposta del popover
Il pulsante "Esegui ora il cron" lancia l'aggiornamento forzato come farebbe phpBB. In fondo alla pagina è disponibile un rapporto testuale pronto da copiare (privo di chiavi private) per richiedere assistenza.

🔄 Aggiornare da una versione precedente
Carica i nuovi file sovrascrivendo quelli vecchi.
In ACP, disattiva e riattiva l'estensione (ATTENZIONE: non cliccare su "Elimina dati").
Le migrazioni aggiorneranno il numero di versione e (se provieni da versioni antecedenti alla 1.0.0) aggiungeranno la scheda Check-up.

🗑️ Disinstallazione
Per rimuovere completamente l'estensione:
Disattivala dal pannello di amministrazione.
Clicca su "Elimina dati".
Verranno rimossi: la colonna nel database, le tabelle specifiche, le impostazioni e le immagini precedentemente caricate.
