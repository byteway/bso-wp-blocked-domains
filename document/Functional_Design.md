# Functioneel Ontwerp - BSO Block Email Domains

**Plugin:** `bso-blocked-domains`  
**Versie:** 2.0.0  
**Auteur:** Byteway Software Ontwikkeling  
**Datum:** 3 juli 2026  
**Doelplatform:** WordPress

---

## Inhoudsopgave

1. [Inleiding en doel](#1-inleiding-en-doel)
2. [Architectuuroverzicht](#2-architectuuroverzicht)
3. [Datamodel](#3-datamodel)
4. [Beheeromgeving (Admin)](#4-beheeromgeving-admin)
5. [Publieke registratieblokkering](#5-publieke-registratieblokkering)
6. [Import, export en herstel](#6-import-export-en-herstel)
7. [Validatie- en bedrijfslogica](#7-validatie--en-bedrijfslogica)
8. [Klassen- en modulestructuur](#8-klassen--en-modulestructuur)
9. [Activatie en deinstallatie](#9-activatie-en-deinstallatie)
10. [Assets, lokalisatie en tooling](#10-assets-lokalisatie-en-tooling)
11. [Rollen, toegang en beveiliging](#11-rollen-toegang-en-beveiliging)

---

## 1. Inleiding en doel

De plugin **BSO Block Email Domains** voorkomt dat gebruikersaccounts worden aangemaakt of bijgewerkt met e-mailadressen van domeinen die op een beheerde blokkeerlijst staan.

De plugin ondersteunt:

- blokkering bij publieke registratie
- blokkering bij admin-profielupdates
- beheer van geblokkeerde domeinen via WordPress admin
- import van grote domeinlijsten
- export van de huidige domeinlijst als CSV
- undo na verwijderacties
- optionele shortcode voor publieke uitleg

### Doelgroepen

| Rol | Doel |
|-----|------|
| Beheerder (`manage_options`) | Domeinen beheren, importeren, exporteren en corrigeren |
| Publieke bezoeker | Registratie wordt geweigerd bij een geblokkeerd e-maildomein |
| Sitebeheer | Betrouwbare en controleerbare beheersing van toegestane registratiedomeinen |

---

## 2. Architectuuroverzicht

```mermaid
graph TD
	WP[WordPress Core]

	subgraph Plugin[BSO Block Email Domains v2]
		MAIN[bso-block-domain.php\nBootstrap]
		DB[includes/class-bso-db.php\nDatalaag]
		VAL[includes/domain-validation.php\nValidatie + registratiehooks]
		SRV[includes/class-bso-domain-service.php\nDomeinservice]
		FE[includes/frontend-ui.php\nShortcode + publieksuitleg]
		ADM[admin/class-bso-admin-page.php\nAdmin UI + handlers]
	end

	subgraph Data[Database]
		TBL[(wp_bso_blocked_domains)]
	end

	subgraph Browser[Browser]
		ADMIN_UI[Admin beheerpagina]
		REG_UI[Registratie / profielupdate]
		INFO_UI[Uitlegpagina met shortcode]
	end

	WP --> MAIN
	MAIN --> DB
	MAIN --> VAL
	MAIN --> SRV
	MAIN --> FE
	MAIN --> ADM
	DB --> TBL
	VAL --> DB
	SRV --> DB
	ADM --> SRV
	ADM --> DB
	ADMIN_UI --> ADM
	REG_UI --> VAL
	INFO_UI --> FE
```

---

## 3. Datamodel

De plugin gebruikt een dedicated tabel voor opslag van geblokkeerde domeinen.

```mermaid
erDiagram
	BSO_BLOCKED_DOMAINS {
		BIGINT id PK
		VARCHAR domain UK
		DATETIME created_at
		DATETIME updated_at
	}
```

### Tabel: `wp_bso_blocked_domains`

| Veld | Type | Omschrijving |
|------|------|--------------|
| `id` | BIGINT(20) UNSIGNED | Primaire sleutel, auto-increment |
| `domain` | VARCHAR(255) | Geblokkeerd domein, uniek |
| `created_at` | DATETIME | Aanmaakmoment record |
| `updated_at` | DATETIME | Laatste wijzigingsmoment |

### Datakarakteristieken

- unieke opslag van domeinen op database-niveau
- zoeken via `LIKE`
- paging via `LIMIT/OFFSET`
- import negeert duplicaten functioneel en technisch

---

## 4. Beheeromgeving (Admin)

### Menu en toegang

De plugin registreert een instellingenpagina onder:

- **Instellingen > Block Email Domains**

```mermaid
graph LR
	SETTINGS[WordPress Settings]
	SETTINGS --> BED[Block Email Domains\nadmin/class-bso-admin-page.php]
```

Vereiste capability: `manage_options`

### Kernfuncties in beheerpagina

1. Domein handmatig toevoegen
2. Domein bewerken
3. Selectie verwijderen
4. Alles verwijderen op basis van huidig filter
5. Undo na verwijderen
6. Zoeken en pagineren
7. Importeren via tekstveld
8. Exporteren van de huidige lijst als CSV

![Domein toevoegen](../image/admin-add-domain.png)

![Filter blocked domains](../image/admin-filter-blocked-domains.png)

### Procesflow: lijstbeheer

```mermaid
flowchart TD
	A[Admin opent beheerpagina] --> B[Zoekterm + page size bepalen]
	B --> C[Domeinen uit database ophalen]
	C --> D[Overzicht tonen]
	D --> E{Actie?}
	E -- Toevoegen --> F[Service add_domain]
	E -- Bewerken --> G[Service update_domain]
	E -- Verwijderen --> H[Service delete_domains]
	H --> I[Undo key opslaan in transient]
	E -- Export --> J[CSV output huidige filter]
	E -- Import --> K[Service import_init + import_chunk]
```

---

## 5. Publieke registratieblokkering

De plugin controleert e-maildomeinen bij registratie- en profielworkflows.

### Blokkeerpunt 1: publieke registratie

- Hook: `registration_errors`
- Controle: domein uit ingevoerd e-mailadres vergelijken met blokkeerlijst
- Gedrag: foutmelding toevoegen bij match

### Blokkeerpunt 2: admin profielupdate

- Hook: `user_profile_update_errors`
- Controle identiek aan publieke registratie

### Procesflow blokkering

```mermaid
flowchart TD
	A[Gebruiker verstuurt e-mail] --> B[Extract domain na @]
	B --> C[Normaliseer domein]
	C --> D{Domein geblokkeerd?}
	D -- Ja --> E[Voeg blocked_email_domain fout toe]
	D -- Nee --> F[Geen blokkering]
```

### Publieke uitleg

Voor informatie aan eindgebruikers is de shortcode beschikbaar:

```text
[bso_blocked_domain_info]
```

![Nieuwe registratie](../image/public-new-registration.png)

---

## 6. Import, export en herstel

### Import

- invoer: één domein per regel
- lege regels worden genegeerd
- ongeldige regels worden overgeslagen
- duplicaten worden niet dubbel opgeslagen
- grote imports worden chunkgewijs verwerkt

### Export

- exporteert de huidige lijst of gefilterde lijst als CSV
- output bevat minimaal kolom `domain`

![Bulk create blocks](../image/admin-bulk-create-blocks.png)

![Export to CSV](../image/admin-export-to-csv.png)

### Undo

- verwijderde domeinen worden tijdelijk opgeslagen in een transient
- beheerder kan direct na verwijdering herstel uitvoeren

![Bulk delete](../image/admin-bulk-delete.png)

```mermaid
flowchart TD
	A[Delete request] --> B[Validatie + capability + nonce]
	B --> C[Opslaan deleted domains in transient]
	C --> D[Verwijderen uit DB]
	D --> E[Toon undo-link]
	E --> F{Undo gekozen?}
	F -- Ja --> G[Restore domains]
	F -- Nee --> H[Geen herstel]
```

---

## 7. Validatie- en bedrijfslogica

### Domeinvalidatie

De validatielaag controleert onder andere:

- geen spaties of `@`
- minimaal één punt in domein
- geen leading of trailing punt
- maximale lengte 253 tekens
- labels maximaal 63 tekens
- labels mogen niet beginnen of eindigen met `-`
- alleen `a-z`, `0-9` en `-`

### Normalisatie

- trimmen
- lowercase
- sanitization via WordPress helpers
- IDN omzetting naar ASCII indien beschikbaar

### Importlogica

- regels worden opgesplitst per newline
- geldige regels naar `valid`
- ongeldige regels naar `invalid`
- resultaten worden gededupliceerd

```mermaid
flowchart LR
	RAW[Ruwe regel] --> NORM[normalize domain]
	NORM --> VALID{valid?}
	VALID -- Ja --> OK[naar valid set]
	VALID -- Nee --> BAD[naar invalid set]
```

---

## 8. Klassen- en modulestructuur

```mermaid
classDiagram
	class BSO_DB {
		+table_name()
		+create_table()
		+drop_table()
		+is_domain_blocked(domain)
		+get_domains(search, per_page, offset)
		+count_domains(search)
		+get_existing_domains(domains)
		+insert_domain(domain)
		+insert_domains_bulk(domains)
		+update_domain(old, new)
		+delete_domains(domains)
	}

	class BSO_Domain_Service {
		+normalize_domains(domains)
		+add_domain(domain)
		+update_domain(old_domain, new_domain)
		+delete_domains(domains, store_undo)
		+restore_domains(undo_key)
		+parse_import_lines(lines)
		+import_init(raw_input)
		+import_chunk(import_key, start, length)
	}

	class BSO_Admin_Page {
		+register_menu()
		+render_page()
		+handle_add_domain()
		+handle_update_domain()
		+handle_delete_domains()
		+handle_import_domains()
		+handle_restore_domains()
		+handle_export_domains()
	}

	class Validation {
		+bso_domain_to_ascii(domain)
		+bso_normalize_domain(domain)
		+bso_is_valid_domain(domain)
		+bso_extract_domain_from_email(email)
		+bso_validate_registration_domain(errors, login, email)
		+bso_validate_profile_domain(errors, update, user)
	}

	class Frontend_UI {
		+bso_shortcode_blocked_domain_info(atts)
	}

	BSO_Domain_Service --> BSO_DB : gebruikt
	BSO_Admin_Page --> BSO_Domain_Service : gebruikt
	Validation --> BSO_DB : lookup blokkade
```

---

## 9. Activatie en deinstallatie

### Activatie

Bij activatie wordt de tabel aangemaakt via `register_activation_hook` en `dbDelta`.

```mermaid
flowchart TD
	A[Plugin activate] --> B[BSO_DB::create_table]
	B --> C[CREATE TABLE if needed]
	C --> D[UNIQUE domain index actief]
```

### Deinstallatie

Bij uninstall verwijdert `uninstall.php` de tabel `wp_bso_blocked_domains`.

```mermaid
flowchart TD
	A[Plugin uninstall] --> B[Drop table wp_bso_blocked_domains]
	B --> C[Alle blocked domains verwijderd]
```

---

## 10. Assets, lokalisatie en tooling

### Assets

De v2-plugin gebruikt geen aparte JavaScript-adminbundel meer voor kernfunctionaliteit; beheeracties lopen server-side via `admin-post.php`.

### Lokalisatie

- text domain: `block-email-domains`
- laadpunt via `load_plugin_textdomain`
- foutmeldingen en beheerteksten zijn vertaalbaar

### Tooling

- releasecontrole via `document/Release_Checklist_v2.md`
- handmatige validatie via `document/E2E_Testplan_v2.md`

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
		IMP[Import export]
		REG[Registratiecontrole]
		PROF[Profielupdatecontrole]
	end

	ADMIN --> MNG
	ADMIN --> IMP
	USER --> REG
	ADMIN --> PROF
```

### Beveiligingsmaatregelen

- capability checks op admin-acties
- nonce-validatie op mutaties
- input sanitization op domeinen, zoekvelden en sleutels
- prepared statements in querypaden
- unieke databaseconstraint op `domain`

### Toegangsmatrix

| Actie | Anoniem | Ingelogde gebruiker | Admin |
|------|---------|---------------------|-------|
| Registreren met geblokkeerd domein | Geblokkeerd | Geblokkeerd | n.v.t. |
| Domeinen beheren | Nee | Nee | Ja |
| Import/export uitvoeren | Nee | Nee | Ja |
| Gebruiker met geblokkeerd domein opslaan in admin | n.v.t. | n.v.t. | Geblokkeerd |

---

*Gegenereerd op 3 juli 2026 - BSO Block Email Domains v2.0.0*
