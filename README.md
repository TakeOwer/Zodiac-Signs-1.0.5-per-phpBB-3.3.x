# Zodiac Signs 1.0.5 per phpBB 3.3

Segno zodiacale per gli iscritti (scelto in automatico dalla data di nascita, l'utente ha sempre l'ultima parola), dodici icone animate originali, oroscopo settimanale e mensile aggiornato da un cron.

## Installazione
1. Carica la cartella `salvocortesiano/zodiacsigns` in `ext/` (percorso finale: `ext/salvocortesiano/zodiacsigns/composer.json`).
2. ACP > Personalizza > Gestione estensioni > **Zodiac Signs** > Attiva.
3. ACP > Estensioni > Zodiac Signs: rivedi le quattro schede.
4. Se vuoi caricare immagini tue, la cartella `images/zodiacsigns/` viene creata al primo caricamento; se il server non lo permette creala a mano con permessi 755.

## Fonti dell'oroscopo (scheda "Oroscopo" > "Fonti da usare")
- **Gratuite (inglese)**: due fonti già configurate, nessuna chiave.
- **API personali**: solo le API che aggiungi tu in "Fonti dell'oroscopo", ciascuna con la sua lingua (italiano o inglese).
- **API personali con riserva gratuita**: prima le tue API, poi le gratuite se le tue non rispondono.

Per ogni lingua le fonti vengono provate in ordine di priorità; se nessuna risponde resta l'ultimo testo valido. Con "Lingua del visitatore" chi usa il forum in italiano legge il testo italiano e gli altri quello inglese.

### Esempio di API a pagamento (schema tipico RapidAPI)
- Indirizzo settimanale: `https://nome-api.p.rapidapi.com/horoscope?sign={sign}&period=weekly`
- Intestazione della chiave: `X-RapidAPI-Key`, chiave: la tua chiave
- Altre intestazioni: `X-RapidAPI-Host: nome-api.p.rapidapi.com`
- Percorso del testo: quello indicato nella documentazione (es. `data.text`)
Salva e premi **Prova**: vedrai l'inizio del testo ricevuto o l'errore.

## Disinstallazione
Disattiva e poi "Elimina dati": vengono rimossi colonna, tabelle, impostazioni e le immagini caricate.

## Check-up (dalla 1.0.1)
ACP > Estensioni > Zodiac Signs > **Check-up** controlla server (PHP, cURL, OpenSSL, cifratura), database, impostazioni e gruppi, immagini e file di lingua, punti di aggancio degli stili attivi (anche quelli ereditati), calcolo del segno, chiavi API, fonti (configurazione e, a richiesta, prova via rete su 1 o 12 segni con codice HTTP, tempi e percorso JSON), testi salvati, cron e risposta del popover. Il pulsante "Esegui ora il cron" lancia l'aggiornamento come farebbe phpBB. In fondo c'è un rapporto testuale da copiare, senza chiavi.

## Aggiornare da una versione precedente
Carica i file sopra quelli vecchi, poi in ACP disattiva e riattiva l'estensione (senza eliminare i dati): le migrazioni aggiornano il numero di versione e, dalla 1.0.0, aggiungono la scheda Check-up.
