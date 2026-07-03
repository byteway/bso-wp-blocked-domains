# Release Notes - BSO Block Email Domains 2.0.0

## Releasegegevens

- Product: BSO Block Email Domains
- Versie: 2.0.0
- Datum: 3 juli 2026
- Status: GO

## Samenvatting

Versie 2.0.0 is een functionele herbouw van de plugin met een opgeschoonde architectuur, een centrale servicelaag, een server-side adminflow en volledige handmatige E2E-validatie.

## Belangrijkste wijzigingen

### Nieuwe architectuur

- opgesplitste modules voor datalaag, validatie, service, admin en front-end
- eenvoudige bootstrap zonder legacy AJAX-afhankelijkheid
- duidelijke scheiding tussen querylogica en bedrijfslogica

### Admin beheer

- instellingenpagina onder Instellingen > Block Email Domains
- domeinen toevoegen, bewerken en verwijderen
- filteren en pagineren
- instelbare page size
- CSV export van huidige filter
- undo na delete

### Import en herstel

- import via tekstinvoer, één domein per regel
- ongeldige regels worden genegeerd
- duplicaten worden niet dubbel opgeslagen
- grote imports worden in chunks verwerkt

### Front-end validatie

- registratie met geblokkeerd domein wordt geweigerd
- profielupdate naar geblokkeerd domein wordt geweigerd
- consistente foutmelding voor eindgebruikers
- shortcode `[bso_blocked_domain_info]` beschikbaar voor publieksuitleg

### Security

- capability checks op beheeracties
- nonce checks op mutaties
- input sanitization
- prepared statements in querypaden

## Validatie en kwaliteit

Handmatig gevalideerd via E2E suites:

- Suite A: datamodel en lifecycle
- Suite B: admin UI beheer
- Suite C: import
- Suite D: front-end validatie
- Suite E: security

Resultaat:

- releasebesluit: GO

## Bekende restpunten

- import preview bestaat nog niet als aparte bevestigingsstap
- bulk insert kan later geoptimaliseerd worden naar multi-row inserts
- geautomatiseerde tests ontbreken nog

## Relevante documenten

- `document/v2.md`
- `document/E2E_Testplan_v2.md`
- `document/Release_Checklist_v2.md`
- `document/Handoff_Runbook_Beheerder.md`
- `document/Technical_Design.md`
- `document/Functional_Design.md`

## Aanbevolen vervolgstappen

1. productie-installatie controleren op een schone WordPress instance
2. release tag aanmaken voor 2.0.0
3. beheerteam instrueren met het handoff runbook

---

Laatste update: 2026-07-03
