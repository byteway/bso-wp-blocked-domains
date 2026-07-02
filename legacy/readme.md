# BSO Block Email Domains

Blokkeert registratie van nieuwe WordPress-gebruikers wanneer een geblokkeerd e-maildomein wordt gebruikt.
De plugin werkt zowel op de publieke registratieflow als in de adminflow (gebruiker aanmaken/bewerken).

## Overzicht

- DB-backed opslag in een dedicated tabel: `{prefix}bso_blocked_domains`
- Beheer via WordPress admin (Options page)
- Import van domeinen uit tekstbestand (1 domein per regel)
- Strikte domeinvalidatie met invalid-line rapportage
- Chunked import met voortgang voor grote lijsten
- Zoeken, pagineren en page-size instellen via `WP_List_Table`
- Per-regel acties: toevoegen, bewerken, verwijderen
- Bulk delete, inclusief delete-all-matching op zoekresultaten
- CSV export van gefilterde/zichtbare lijst
- CSV export van ongeldige importregels
- IDN/punycode ondersteuning (normalisatie naar ASCII)
- Undo-mechanisme na verwijderen (transient-based)
- Lokale bundling van SweetAlert2 (geen CDN vereist)

## Installatie

1. Plaats de map `bso-blocked-domains` in `wp-content/plugins/`.
2. Activeer de plugin in WordPress via **Plugins**.
3. Bij activatie wordt automatisch de tabel `{prefix}bso_blocked_domains` aangemaakt.

## Gebruik

### Beheer en import

Ga naar **Instellingen > Block Email Domains**.

- Upload een `.txt` bestand met 1 domein per regel.
- Bekijk de importpreview met:
	- totaal aantal regels
	- unieke geldige domeinen
	- duplicaten
	- ongeldige regels
- Start import in chunks; voortgang wordt in de UI getoond.

### Beheer geblokkeerde domeinen

- Zoek op domein
- Pas paginagrootte toe
- Voeg een nieuw domein toe
- Bewerk/verwijder losse records
- Verwijder meerdere records of alle zoekmatches
- Exporteer huidige lijst/filter naar CSV

## Structuur

```text
bso-blocked-domains/
├── bso-block-domain.php
├── bso-admin.js
├── uninstall.php
├── admin/
│   ├── admin.php
│   └── class-bso-list-table.php
├── includes/
│   ├── class-bso-db.php
│   ├── ajax.php
│   ├── ajax-impl.php
│   └── functions-helpers.php
├── languages/
│   ├── block-email-domains.pot
│   └── nl_NL.po
└── vendor/
		└── sweetalert2.min.js
```

## Beveiliging en validatie

- AJAX endpoints controleren capability: `manage_options`
- Nonce-validatie op beheeracties en import/export acties
- SQL-aanroepen via `$wpdb->prepare` en sanitization helpers
- Domeinen worden gevalideerd en genormaliseerd (incl. IDN/punycode)

## Uninstall gedrag

- `uninstall.php` verwijdert de plugin-tabel (`DROP TABLE IF EXISTS`).
- Dit is destructief. Maak vooraf een back-up als je data wilt behouden.

## Changelog

### v1.7

- Refactor naar `includes/` en `admin/` layout
- Adminlijst overgezet naar `WP_List_Table`
- SweetAlert2 lokaal gebundeld onder `vendor/`
- `uninstall.php` toegevoegd en DB-logica geconsolideerd
- Dedicated admin JS (`bso-admin.js`) en verbeterde UI feedback
- Undo/snackbar voor verwijderacties
- Per-regel bewerken en direct domein toevoegen
- Vertaalbaarheid verbeterd (`block-email-domains` text domain)

### v1.5

- IDN/punycode ondersteuning voor validatie/opslag
- Per-regel edit toegevoegd
- CSV export voor volledige of gefilterde lijst
- Verbeterde bevestigingen met modals/toasts

### v1.4

- Legacy tekstbestand-opslag verwijderd
- Plugin gebruikt uitsluitend DB-opslag
- Beheerpagina vereenvoudigd rond DB-workflow

### v1.3

- Introductie dedicated DB-tabel `{prefix}bso_blocked_domains`
- Chunked import met duplicate handling
- Beheer-UI met zoeken, paginatie en bulk-acties
- CSV download van invalid importregels

### v1.2

- Striktere per-regel validatie bij import
- Invalid-line rapportage met regelnummers/preview
- Verbeterde importworkflow en bugfixes

## Ontwikkelnotities

- Vernieuw POT-bestand met:

```bash
wp i18n make-pot . languages/block-email-domains.pot
```

- Voor productie: gebruik een officiële SweetAlert2 distributie/versionering.

## Screenshots

### Settings / Management

![BED-Settings](https://github.com/user-attachments/assets/6bb103d8-7312-4e0a-ab53-f65432170598)

### New user registration blocked domain

![BED-New-user](https://github.com/user-attachments/assets/b3b6cfbb-d664-450a-9022-42acf61e7e39)
