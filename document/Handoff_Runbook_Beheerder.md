# Handoff Runbook - Beheerder

## Documentgegevens

- Plugin: BSO Block Email Domains
- Versie: 2.0.0
- Datum: 2026-07-03
- Status: Releasegereed (GO)
- Doelgroep: Functioneel beheerder / WordPress beheerder

## Doel van dit runbook

Dit document beschrijft hoe een beheerder de plugin in gebruik neemt, beheert, controleert en veilig overdraagt. De plugin blokkeert registratie en profielupdates voor e-maildomeinen die op een beheerlijst staan.

## Samenvatting van de plugin

De plugin ondersteunt:

- beheer van geblokkeerde e-maildomeinen via WordPress admin
- import van domeinlijsten
- export van de actuele lijst als CSV
- undo na verwijderacties
- blokkering bij publieke registratie
- blokkering bij admin-profielwijzigingen
- optionele shortcode met uitleg voor eindgebruikers

## Toegang en rechten

Benodigde rechten:

- WordPress rol met capability `manage_options`

Zonder deze rechten kan een gebruiker:

- de beheerpagina niet openen
- geen mutaties uitvoeren
- geen export of herstelactie starten

## Locaties in WordPress

Beheerpagina:

- Instellingen > Block Email Domains

Publieke registratie:

- standaard WordPress registratieflow via `wp-login.php?action=register`

Optionele uitlegpagina:

- pagina met shortcode `[bso_blocked_domain_info]`

## Eerste ingebruikname

Voer deze controle uit na installatie of update:

1. Activeer de plugin.
2. Controleer of de tabel `wp_bso_blocked_domains` is aangemaakt.
3. Open Instellingen > Block Email Domains.
4. Voeg een testdomein toe, bijvoorbeeld `test-1.nl`.
5. Exporteer de lijst en controleer of CSV-download werkt.
6. Verwijder het testdomein of gebruik Undo.
7. Test registratieblokkering met een geblokkeerd domein.

## Dagelijks beheer

### Domein toevoegen

1. Open de beheerpagina.
2. Vul het domein in bij Domein toevoegen.
3. Klik op Toevoegen.
4. Controleer de succesmelding.

Gebruik alleen het domein, niet het volledige e-mailadres.

Correct:

- `voorbeeld.nl`
- `mail.example.com`

Niet correct:

- `gebruiker@voorbeeld.nl`
- `https://voorbeeld.nl`

### Domein bewerken

1. Zoek het bestaande domein in de lijst.
2. Klik op Bewerken.
3. Pas de waarde aan.
4. Klik op Opslaan.

### Domeinen verwijderen

Mogelijkheden:

- selectie verwijderen
- alles verwijderen op basis van huidig zoekfilter

Na verwijderen verschijnt een Undo-optie. Gebruik die direct als een verwijdering onbedoeld was.

### Zoeken en filteren

Gebruik het zoekveld boven de lijst om domeinen op deelstring te filteren. Het filter werkt ook door in:

- lijstweergave
- bulk delete op basis van filter
- CSV export van de huidige set

### Paginagrootte aanpassen

1. Vul bij Items per pagina een nieuwe waarde in.
2. Klik op Opslaan.
3. De instelling blijft bewaard voor volgende bezoeken.

## Importprocedure

Gebruik import voor grotere lijsten.

### Formaat

- één domein per regel
- lege regels zijn toegestaan en worden genegeerd
- duplicaten worden genegeerd
- ongeldige regels worden niet opgeslagen

Voorbeeld:

```text
voorbeeld.nl
example.com
sub.mail.org
```

### Uitvoering

1. Open de beheerpagina.
2. Plak de lijst in het importveld.
3. Klik op Importeren.
4. Controleer na afloop de melding met aantallen toegevoegd en ongeldig.

### Verwacht gedrag

- geldige domeinen worden toegevoegd
- ongeldige domeinen worden overgeslagen
- grote imports worden in chunks verwerkt
- duplicaten veroorzaken geen fout

## Exportprocedure

1. Zet eventueel eerst een zoekfilter.
2. Klik op Exporteer CSV (huidige filter).
3. Sla het bestand op voor audit, migratie of controle.

Het CSV-bestand bevat minimaal de kolom:

- `domain`

## Front-end beheer

### Registratieblokkering

Wanneer een bezoeker zich registreert met een e-mailadres waarvan het domein op de blokkeerlijst staat, wordt registratie geblokkeerd met een duidelijke foutmelding.

### Profielupdateblokkering

Wanneer een beheerder of gebruiker een bestaand profiel wijzigt naar een geblokkeerd domein, wordt opslaan tegengehouden.

### Shortcode voor uitleg

Gebruik deze shortcode op een publieke pagina:

```text
[bso_blocked_domain_info]
```

Doel:

- uitleg geven waarom sommige e-maildomeinen niet zijn toegestaan
- bezoekers helpen een alternatief e-mailadres te gebruiken

## Operationele controles

Voer periodiek deze controles uit:

1. Controleer of de beheerpagina opent zonder fouten.
2. Controleer of toevoegen, verwijderen en zoeken nog werken.
3. Controleer of export CSV correct downloadt.
4. Controleer of registratieblokkering nog actief is.
5. Controleer na updates of de database-tabel nog aanwezig is.

## Incidenten en foutafhandeling

### Probleem: domein kan niet worden toegevoegd

Mogelijke oorzaken:

- domein is ongeldig
- domein bestaat al
- gebruiker heeft onvoldoende rechten
- nonce of sessie is verlopen

Actie:

1. Herlaad de pagina.
2. Controleer of het domein correct is ingevoerd.
3. Controleer of je nog bent ingelogd als beheerder.
4. Probeer opnieuw.

### Probleem: registratie wordt niet geblokkeerd

Controleer:

1. of het domein exact in de lijst staat
2. of registratie in WordPress zelf actief is
3. of een andere plugin de registratieflow overschrijft
4. of je test met alleen het domein en niet met een typo

### Probleem: gebruiker ziet lege of geweigerde beheerpagina

Meest waarschijnlijk:

- gebruiker heeft geen `manage_options`

Actie:

1. controleer de rol van de gebruiker
2. test opnieuw met een Administrator-account

### Probleem: undo werkt niet meer

Uitleg:

- undo is tijdelijk beschikbaar via een transient
- na verloop van tijd vervalt deze herstelactie

Actie:

1. importeer of voeg de verwijderde domeinen opnieuw toe
2. gebruik zo nodig een eerdere CSV-export als bron

## Veiligheid en beheergrenzen

Beveiligingsmaatregelen die zijn bevestigd:

- nonce-checks op muterende acties
- capability-checks op admin-acties
- input sanitization
- prepared statements in de datalaag

Beheeradvies:

1. geef alleen vertrouwde beheerders toegang
2. houd exports buiten publieke mappen
3. test na WordPress core- of pluginupdates kort de registratieflow

## Back-up en rollback

### Voor een wijzigingsronde

1. exporteer de huidige domeinlijst als CSV
2. maak een database-back-up van de WordPress site
3. noteer pluginversie en datum van wijziging

### Rollback bij functioneel probleem

1. deactiveer de plugin tijdelijk als registraties onterecht worden geblokkeerd
2. herstel indien nodig de code naar de vorige stabiele release
3. importeer de eerder geëxporteerde CSV opnieuw als lijst verloren is gegaan

## Overdrachtsinformatie

Bij overdracht aan een nieuwe beheerder minimaal meegeven:

1. locatie van de beheerpagina
2. uitleg van import/export/undo
3. gebruik van de shortcode
4. testprocedure voor registratieblokkering
5. link naar het E2E-testplan en release-checklist

Relevante documenten:

- `document/v2.md`
- `document/E2E_Testplan_v2.md`
- `document/Release_Checklist_v2.md`
- `document/Technical_Design.md`
- `document/Functional_Design.md`

## Release- en teststatus

Huidige status:

- E2E Suites A t/m E: geslaagd
- Releasebesluit: GO
- Git status bij afronding: schoon

## Open restpunten

Er zijn geen open blokkerende functionele bevindingen vanuit het uitgevoerde E2E-testplan.

Nog mogelijke vervolgstappen:

1. technische documentatie bijwerken als architectuur verder wijzigt
2. productiecheck uitvoeren na livegang
3. beheerteam instrueren op import/export en rollback

---

Laatste update: 2026-07-03
