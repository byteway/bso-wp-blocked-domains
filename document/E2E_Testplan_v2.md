# E2E Testplan - BSO Block Email Domains v2

## Doel

Valideren dat v2 functioneel correct werkt in WordPress voor adminbeheer, registratieblokkering en releasekwaliteit.

## Testomgeving

- WordPress versie: vul in
- PHP versie: vul in
- Plugin versie: 2.0.0
- Testrollen: Administrator, anonieme bezoeker

## Voorbereiding

- [x] Plugin geactiveerd
- [x] DB tabel aanwezig (`wp_bso_blocked_domains`)
- [ ] Admin account met `manage_options`
- [ ] WordPress registratie ingeschakeld

## Suite A - Datamodel en lifecycle

- [x] A1 Activatie maakt tabel aan
: Verwacht: tabel bestaat met `domain` unique key
- [x] A2 Deactivatie laat data bestaan
: Verwacht: data blijft staan
- [x] A3 Uninstall verwijdert tabel
: Verwacht: tabel verwijderd

## Suite B - Admin UI beheer

- [x] B1 Pagina zichtbaar onder Instellingen > Block Email Domains
: Verwacht: pagina opent zonder fout
- [x] B2 Domein toevoegen
: Input `test-1.nl`
: Verwacht: succesmelding en domein zichtbaar in lijst
- [x] B3 Domein bewerken
: Wijzig `test-1.nl` naar `test-2.nl`
: Verwacht: succesmelding en nieuwe waarde in lijst
- [x] B4 Selectie verwijderen
: Selecteer 1+ domeinen
: Verwacht: domeinen verwijderd en undo zichtbaar
- [x] B5 Undo herstellen
: Klik Ongedaan maken
: Verwacht: verwijderde domeinen terug in lijst
- [x] B6 Zoekfilter
: Zoek op deelstring
: Verwacht: alleen matching records zichtbaar
- [x] B7 Paginagrootte opslaan
: Zet op 10/50/100
: Verwacht: paginatie past aan en blijft bewaard
- [x] B8 CSV export
: Exporteer huidige filter
: Verwacht: geldig CSV met kolom `domain`

## Suite C - Import

- [x] C1 Import geldige regels
: Voorbeeld: `alpha.nl`, `beta.com`
: Verwacht: beide toegevoegd
- [x] C2 Import met ongeldige regels
: Voorbeeld: `bad domain`, `@domain`
: Verwacht: ongeldige regels overgeslagen, geen fatale fout
- [x] C3 Import met duplicaten
: Voorbeeld: `alpha.nl` twee keer
: Verwacht: slechts 1 record opgeslagen
- [x] C4 Grote import (min. 1000 regels)
: Verwacht: geen timeout, chunking verwerkt alles

## Suite D - Front-end validatie

- [x] D1 Registratie met geblokkeerd domein
: E-mail `user@test-2.nl`
: Verwacht: blokkade met duidelijke melding
- [x] D2 Registratie met toegestaan domein
: E-mail `user@example.org`
: Verwacht: geen plugin-blokkade
- [x] D3 Profielupdate naar geblokkeerd domein
: Admin wijzigt user e-mail naar geblokkeerd domein
: Verwacht: blokkade met dezelfde foutmelding
- [x] D4 Shortcode output
: Plaats `[bso_blocked_domain_info]` op pagina
: Verwacht: titel + uitlegtekst zichtbaar

## Suite E - Beveiliging

- [ ] E1 Nonce ontbreekt bij mutatie
: Verwacht: request geblokkeerd
- [ ] E2 Onvoldoende rechten (geen manage_options)
: Verwacht: beheeracties geblokkeerd

## Go/No-Go

- [ ] GO voor release
- [ ] NO-GO (blokkades open)

## Bevindingen

- Open issues: geen voor Suite A t/m D
- Opgeloste issues:
- Rest-risico: alleen Suite E (security) nog handmatig open

---

Laatste update: 2026-07-02
