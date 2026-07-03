# Release Checklist - BSO Block Email Domains v2

## Versiegegevens

- Releaseversie: 2.0.0
- Datum: 2026-07-02
- Branch: main

## Code en quality gates

- [x] Laatste commits gepusht naar `origin/main`
- [x] Geen lokale uncommitted changes (`git status` clean)
- [x] Basale PHP syntax controle zonder fouten
- [x] E2E testplan uitgevoerd
- [ ] Kritieke bugs opgelost of geaccepteerd met risico

## Functionele dekking

- [x] Datamodel actief (create/drop lifecycle)
- [x] Admin beheer werkt (add/edit/delete/search/paging)
- [x] Import flow werkt (incl. grote input)
- [x] Export CSV werkt
- [x] Undo na delete werkt
- [x] Registratieblokkering werkt
- [x] Profielupdateblokkering werkt
- [x] Shortcode `[bso_blocked_domain_info]` werkt

## Security en beheer

- [x] Capability checks aanwezig op admin-acties
- [x] Nonce checks aanwezig op mutaties
- [x] Input sanitization toegepast
- [x] SQL gebruikt prepared statements waar nodig

## Documentatie

- [x] `document/v2.md` status bijgewerkt
- [x] `document/E2E_Testplan_v2.md` resultaten ingevuld
- [ ] `document/Technical_Design.md` bijgewerkt indien architectuur wijzigde
- [ ] `document/Functional_Design.md` bijgewerkt indien functionaliteit wijzigde

## Distributie

- [ ] Tag of release-notitie aangemaakt
- [ ] Installatie gecontroleerd op schone WordPress instance
- [ ] Rollback pad beschreven

## Post-release

- [ ] Eerste productiecheck uitgevoerd
- [ ] Monitoren op errors/regressies afgesproken
- [ ] Hotfix-procedure klaar

---

Eindbesluit:
- [x] GO
- [ ] NO-GO

Toelichting:
Alle E2E suites A t/m E zijn handmatig gevalideerd. Plugin is releasegereed onder de geteste WordPress-omgeving.
