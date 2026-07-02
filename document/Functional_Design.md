# Functioneel Ontwerp - BSO Block Email Domains

**Plugin:** `bso-blocked-domains`  
**Versie:** 1.7  
**Auteur:** Byteway Software Ontwikkeling  
**Datum:** 28 juni 2026  
**Doelplatform:** WordPress

---

## Inhoudsopgave

1. [Inleiding en doel](#1-inleiding-en-doel)
2. [Architectuuroverzicht](#2-architectuuroverzicht)
3. [Datamodel](#3-datamodel)
4. [Beheeromgeving (Admin)](#4-beheeromgeving-admin)
5. [Publieke registratieblokkering](#5-publieke-registratieblokkering)
6. [AJAX API en verwerkingsflows](#6-ajax-api-en-verwerkingsflows)
7. [Validatie- en bedrijfslogica](#7-validatie--en-bedrijfslogica)
8. [Klassen- en modulestructuur](#8-klassen--en-modulestructuur)
9. [Activatie en deinstallatie](#9-activatie-en-deinstallatie)
10. [Assets, lokalisatie en tooling](#10-assets-lokalisatie-en-tooling)
11. [Rollen, toegang en beveiliging](#11-rollen-toegang-en-beveiliging)

---

## 1. Inleiding en doel

De plugin **BSO Block Email Domains** voorkomt dat gebruikersaccounts worden aangemaakt of bijgewerkt met e-mailadressen van domeinen die op een blokkeerlijst staan.

De plugin ondersteunt:
- Blokkering bij publieke registratie
- Blokkering bij admin-profielupdates
- Beheer van geblokkeerde domeinen via admin UI
- Import/export workflows voor grote domeinlijsten

### Doelgroepen

| Rol | Doel |
|-----|------|
| Beheerder (`manage_options`) | Domeinen importeren, beheren, corrigeren, verwijderen, exporteren |
| Publieke bezoeker | Registratie wordt geweigerd bij geblokkeerd e-maildomein |
| Sitebeheer (operationeel) | Controleerbare, schaalbare en onderhoudbare domeinblokkering |

---

## 2. Architectuuroverzicht

```mermaid
graph TD
	WP[WordPress Core]

	subgraph Plugin[BSO Block Email Domains]
		MAIN[bso-block-domain.php\nBootstrap + includes]
		HELP[includes/functions-helpers.php\nValidatie + helpers + registratie hooks]
		DB[includes/class-bso-db.php\nDB laag]
		AJAX_REG[includes/ajax.php\nAJAX routes]
		AJAX_IMPL[includes/ajax-impl.php\nAJAX handlers]
		ADMIN[admin/admin.php\nAdmin pagina renderer]
		LIST[admin/class-bso-list-table.php\nWP_List_Table]
		JS[bso-admin.js\nAdmin interacties]
	end

	subgraph Data[Database]
		TBL[(wp_bso_blocked_domains)]
	end

	subgraph Browser[Browser]
		ADMIN_UI[WordPress admin UI]
		REG_UI[Registratieformulier]
	end

	WP --> MAIN
	MAIN --> HELP
	MAIN --> DB
	MAIN --> AJAX_REG
	MAIN --> AJAX_IMPL
	MAIN --> ADMIN
	ADMIN --> LIST
	ADMIN --> JS

	DB --> TBL
	AJAX_IMPL --> TBL
	LIST --> TBL
	HELP --> TBL

	ADMIN_UI --> JS
	REG_UI --> HELP
```

### Subfolder-dekking

Onderstaande onderdelen zijn meegenomen in dit ontwerp:
- `admin/`
- `includes/`
- `languages/`
- `vendor/`
- `tools/`
- rootbestanden (`bso-block-domain.php`, `bso-admin.js`, `uninstall.php`, `blocked-domains.txt`, `readme.md`)

---

## 3. Datamodel

De plugin gebruikt een dedicated tabel voor opslag van geblokkeerde domeinen.

```mermaid
erDiagram
	BSO_BLOCKED_DOMAINS {
		BIGINT id PK
		VARCHAR domain UK
	}
```

### Tabel: `wp_bso_blocked_domains`

| Veld | Type | Omschrijving |
|------|------|--------------|
| `id` | BIGINT(20) UNSIGNED | Primaire sleutel, auto-increment |
| `domain` | VARCHAR(255) | Geblokkeerd domein, uniek (`UNIQUE KEY`) |

### Datakarakteristieken

- Duplicaten worden genegeerd door `INSERT IGNORE`
- Zoek- en exportacties ondersteunen filtering via `LIKE`
- Opslag is DB-only (geen actieve file-based opslag meer)

---

## 4. Beheeromgeving (Admin)

### Menu en toegang

De plugin registreert een instellingenpagina onder:

- **Instellingen > Block Email Domains**

```mermaid
graph LR
	SETTINGS[WordPress Settings]
	SETTINGS --> BED[Block Email Domains\nadmin/admin.php]
```

Vereiste capability: `manage_options`.

### Kernfuncties in beheerpagina

1. Importbestand uploaden en previewen
2. Import in chunks starten
3. Domein handmatig toevoegen
4. Domein per rij bewerken/verwijderen
5. Bulk delete en delete-all-matching
6. CSV export van (gefilterde) lijst

### Procesflow: import + preview

```mermaid
flowchart TD
	A[Admin uploadt TXT-bestand] --> B[Nonce check save_blocked_domains]
	B --> C[Lees regels en parse]
	C --> D{Regel geldig domein?}
	D -- Ja --> E[Voeg toe aan valid list]
	D -- Nee --> F[Voeg toe aan invalid list met line number]
	E --> G[Toon import samenvatting]
	F --> G
	G --> H[Start Import knop]
```

### Procesflow: lijstbeheer

```mermaid
flowchart TD
	A[Admin opent lijst] --> B[WP_List_Table prepare_items]
	B --> C[Optionele zoekterm]
	C --> D[Paginated query op DB]
	D --> E[Toon rijen]
	E --> F{Actie?}
	F -- Add --> G[AJAX bso_add_domain]
	F -- Edit --> H[AJAX bso_update_domain]
	F -- Delete --> I[AJAX bso_delete_domains]
	F -- Export --> J[AJAX bso_export_list]
	I --> K[Opslaan undo transient]
	K --> L[Optionele restore via bso_restore_domains]
```

---

## 5. Publieke registratieblokkering

De plugin controleert e-maildomeinen bij registratie- en profielworkflows.

### Blokkeerpunt 1: publieke registratie

- Hook: `registration_errors`
- Controle: domein uit user e-mail vergelijken met geblokkeerde lijst
- Gedrag: foutmelding toevoegen bij match

### Blokkeerpunt 2: admin profielupdate

- Hook: `user_profile_update_errors`
- Controle identiek aan publieke registratie

### Procesflow blokkering

```mermaid
flowchart TD
	A[Gebruiker verstuurt e-mail] --> B[Extract domain na @]
	B --> C[Laad blocked domains uit DB]
	C --> D{Domain in blocked list?}
	D -- Ja --> E[Voeg blocked_email_domain fout toe]
	D -- Nee --> F[Geen blokkering]
```

---

## 6. AJAX API en verwerkingsflows

### Beschikbare AJAX acties

| Actie | Doel |
|------|------|
| `bso_add_domain` | Domein toevoegen |
| `bso_update_domain` | Domein bewerken |
| `bso_delete_domains` | Enkele/meerdere/all-matching verwijderen |
| `bso_import_init` | Importset voorbereiden in transient |
| `bso_import_chunk` | Chunkgewijs importeren |
| `bso_export_invalid` | Invalid importregels als CSV exporteren |
| `bso_export_list` | Huidige/gefilterde domeinlijst exporteren |
| `bso_set_page_size` | Paginaformaat instellen |
| `bso_restore_domains` | Verwijderde domeinen herstellen (undo) |

### Procesflow: chunked import

```mermaid
sequenceDiagram
	participant UI as Admin UI (bso-admin.js)
	participant AJ as AJAX
	participant TR as Transient Store
	participant DB as DB Table

	UI->>AJ: bso_import_init(import_preview)
	AJ->>TR: save valid items + invalid items
	AJ-->>UI: key + total + invalid_count

	loop per chunk
		UI->>AJ: bso_import_chunk(key,start,length)
		AJ->>TR: load items
		AJ->>DB: INSERT IGNORE chunk
		AJ-->>UI: imported + merged_total
	end

	UI-->>UI: update progress + reload
```

### Procesflow: delete + undo

```mermaid
flowchart TD
	A[Delete request] --> B[Nonce + capability check]
	B --> C[Opslaan te verwijderen domeinen in transient]
	C --> D[Verwijder records uit DB]
	D --> E[Retourneer deleted_count + undo_key]
	E --> F{Admin kiest restore?}
	F -- Ja --> G[bso_restore_domains met undo_key]
	G --> H[INSERT IGNORE terug in DB]
	F -- Nee --> I[Geen herstel]
```

---

## 7. Validatie- en bedrijfslogica

### Domeinvalidatie

De helperlogica valideert onder andere:

- Geen spaties of `@`
- Minimaal een punt in domein
- Geen leading/trailing punt
- Maximale domeinlengte 253
- Labelregels: max 63, geen leading/trailing `-`, alleen `[A-Za-z0-9-]`

### IDN/punycode verwerking

Bij beschikbaarheid van `idn_to_ascii` wordt domein omgezet naar ASCII-variant voor validatie en opslag.

### Importregels

- Lege regels worden genegeerd
- Geldige regels worden gededupliceerd
- Ongeldige regels worden geregistreerd met regelnummer en originele waarde

```mermaid
flowchart LR
	RAW[Ruwe regel] --> TRIM[trim + sanitize_text_field]
	TRIM --> IDN[bso_domain_to_ascii]
	IDN --> VALID{bso_is_valid_domain}
	VALID -- Ja --> OK[naar valid set]
	VALID -- Nee --> BAD[naar invalid set met line]
```

---

## 8. Klassen- en modulestructuur

```mermaid
classDiagram
	class BSO_DB {
		+create_table()
		+drop_table()
		+insert_domains(domains)
		+get_domains(search, per_page, offset)
		+count_domains(search)
	}

	class BSO_List_Table {
		+get_columns()
		+column_cb(item)
		+column_domain(item)
		+column_actions(item)
		+get_bulk_actions()
		+prepare_items()
	}

	class Admin_Page {
		+bso_admin_menu()
		+bso_admin_page()
	}

	class Ajax_Impl {
		+bso_ajax_add_domain()
		+bso_ajax_update_domain()
		+bso_ajax_delete_domains()
		+bso_ajax_import_init()
		+bso_ajax_import_chunk()
		+bso_ajax_export_invalid()
		+bso_ajax_export_list()
		+bso_ajax_set_page_size()
		+bso_ajax_restore_domains()
	}

	class Helpers {
		+bso_domain_to_ascii(domain)
		+bso_is_valid_domain(domain)
		+bso_parse_import_lines(lines)
		+get_blocked_domains()
	}

	BSO_List_Table --> BSO_DB : gebruikt
	Admin_Page --> BSO_List_Table : rendert
	Ajax_Impl --> BSO_DB : CRUD
	Ajax_Impl --> Helpers : validatie
```

### Mapniveau (onderdelen)

```mermaid
graph TD
	ROOT[bso-blocked-domains]
	ROOT --> A[admin/]
	ROOT --> I[includes/]
	ROOT --> L[languages/]
	ROOT --> V[vendor/]
	ROOT --> T[tools/]
	ROOT --> R1[bso-block-domain.php]
	ROOT --> R2[bso-admin.js]
	ROOT --> R3[uninstall.php]
	ROOT --> R4[blocked-domains.txt]
```

---

## 9. Activatie en deinstallatie

### Activatie

Bij pluginactivatie wordt de DB-tabel aangemaakt via `register_activation_hook` en `dbDelta`.

```mermaid
flowchart TD
	A[Plugin activate] --> B[BSO_DB::create_table]
	B --> C[CREATE TABLE if needed]
	C --> D[UNIQUE domain index actief]
```

### Deinstallatie

Er zijn twee deinstallatiepaden aanwezig:

1. `uninstall.php` met `DROP TABLE IF EXISTS`
2. `register_uninstall_hook(... BSO_DB::drop_table)` in DB-module

Beide verwijderen de plugindata (destructief).

```mermaid
flowchart TD
	A[Plugin uninstall] --> B[Drop table wp_bso_blocked_domains]
	B --> C[Alle blocked domains verwijderd]
```

---

## 10. Assets, lokalisatie en tooling

### Assets

| Bestand | Functie |
|--------|---------|
| `bso-admin.js` | UI interactie, modal/prompt, AJAX calls, importprogress |
| `vendor/sweetalert2.min.js` | Modal dialogs en toasts |

### Lokalisatie

| Map/bestand | Doel |
|-------------|------|
| `languages/block-email-domains.pot` | Vertaaltemplate |
| `languages/nl_NL.po` | Nederlandse vertaling |

### Tooling

| Bestand | Doel |
|--------|------|
| `tools/po2mo.php` | Conversie/ondersteuning vertaalbestanden |

---

## 11. Rollen, toegang en beveiliging

```mermaid
graph TD
	subgraph Rollen
		ADMIN[Admin manage_options]
		USER[Publieke registrant]
	end

	subgraph Functionaliteit
		MNG[Beheer blocked domains]
		AJAX[AJAX beheeracties]
		REG[Registratiecontrole]
		PROF[Profielupdatecontrole]
	end

	ADMIN --> MNG
	ADMIN --> AJAX
	USER --> REG
	ADMIN --> PROF
```

### Beveiligingsmaatregelen

- Capability checks op AJAX endpoints
- Nonce validatie op muterende acties
- Sanitization van inputvelden
- Voorbereide SQL statements waar toegepast
- Isolatie van data in dedicated tabel

### Toegangsmatrix

| Actie | Anoniem | Ingelogde gebruiker | Admin |
|------|---------|---------------------|-------|
| Registreren met geblokkeerd domein | Geblokkeerd | Geblokkeerd | n.v.t. |
| Domeinen beheren | Nee | Nee | Ja |
| Import/export uitvoeren | Nee | Nee | Ja |
| Gebruiker met geblokkeerd domein opslaan in admin | n.v.t. | n.v.t. | Geblokkeerd |

---

## Bijlage - Functionele status

De plugin is functioneel voor productiegebruik als beheerplugin voor domeinblokkering. Door de modulaire opzet (`admin/`, `includes/`, `vendor/`, `languages/`) is de codebase klaar voor doorontwikkeling, zoals extra auditlogging, uitgebreidere rapportage of role-based delegatie.

---

*Gegenereerd op 28 juni 2026 - BSO Block Email Domains v1.7*
