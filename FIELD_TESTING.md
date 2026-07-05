# AMOEC Track — Field Testing Plan

Features and functions that **cannot be verified in the development room** and require
**real-world field testing** — actual GPS movement along the routes, real network dead
zones, multiple devices, a full-length trip, and accumulated live trip data.

> Everything else (CRUD, validation, auth/role scoping, schema constraints, the map
> editor's save path, OSRM/Nominatim endpoints, duration math) has been verified at the
> desk. This document lists only what is left — the things that need a bus, a driver, a
> passenger, and the road.

**Scope:** the Android app (`AMOECTrack`) **and** the web admin (`amoec-web-admin`),
which share one Supabase backend.

---

## How to use this document

Update the **Status** of each item as you test. Record the date, device, route, and any
numbers (ETA error, battery %, delivery latency) in the item's **Notes**.

| Symbol | Meaning |
|---|---|
| ⬜ | Not yet tested |
| 🟡 | In progress / partially tested |
| ✅ | Passed |
| ❌ | Failed (log the defect) |
| ⚠️ | Known gap / blocked (see notes) |

**Priority:** **P1** = required before deployment/defense · **P2** = should have · **P3** = nice to have.

### Equipment / prerequisites
- At least **2 Android phones**: one as **driver** (with a data SIM), one or more as **passenger**.
- A real **AMOEC route** to drive (e.g. Allen ↔ Calbayog, Allen ↔ Catarman).
- A **driver account** (promoted via the web admin) assigned to a **vehicle** and route.
- Known or discoverable **dead-zone stretches** along the route (no cellular signal).
- A way to log: notebook / spreadsheet for ETA vs actual, battery %, timestamps.
- Confirm the Realtime publication migration (`20260430_008_realtime_publication.sql`) is
  deployed, or Realtime silently falls back to the 30 s poll (see item **C1**).

---

## A. Driver app — on the road

### A1 — Live position publishing while driving `P1`  ⬜
- **Why field:** needs real GPS movement along the actual road; a desk emulator only fakes a static point.
- **Steps:** Sign in as driver → start a trip → drive the route. On a second device (or the web admin / passenger map) watch the vehicle move.
- **Pass:** `vehicle_live_location` updates continuously as the bus moves; sampled `location_points` accumulate; the marker tracks the road.

### A2 — Background / screen-off publishing `P1`  ⬜
- **Why field:** OS background restrictions only bite during a real, long, backgrounded trip.
- **Steps:** Start a trip, background the app / lock the screen, keep driving 10–15 min.
- **Pass:** Positions keep publishing; the foreground-service notification stays visible; no gap when the app is not in the foreground.

### A3 — Service survival after process kill `P1`  ⚠️
- **Why field:** the OS kills the service under real memory pressure on long trips. **Known gap** from the audit (no auto-resume after kill).
- **Steps:** Start a trip → force-stop the app (or induce an OS kill) → observe.
- **Pass / record:** Document whether tracking resumes. If it does not (expected), note the UX (does the driver get told?) and whether re-opening the app recovers the active trip.

### A4 — Offline GPS buffering + sync drain `P1`  ⬜
- **Why field:** needs a **real signal loss then regain** while moving — the core dead-zone scenario.
- **Steps:** While driving, enter a no-signal stretch and keep moving through it; exit back into coverage. (`QueuedLocationEntity` buffers offline; `LocationSyncWorker` drains on reconnect.)
- **Pass:** Points recorded during the dead zone are **buffered locally**, then **drained in order** to the server after reconnect — no loss, no duplicates, correct timestamps.

### A5 — Route-snap accuracy `P2`  ⬜
- **Why field:** only meaningful with real GPS along the road.
- **Steps:** Drive the route; compare the published (snapped) position to the actual road on the map.
- **Pass:** Snapped position stays on the route polyline; no large jumps or wrong-road snapping.

### A6 — Occupancy updates `P2`  ⬜
- **Steps:** During a trip, change occupancy (e.g. seats filling) and confirm it propagates to passenger devices.
- **Pass:** Occupancy shown to passengers matches what the driver set, within the refresh window.

### A7 — `DriverLocationBus` single-source position `P2`  ⬜
- **Why field:** verifies there is no duplicate GPS subscription (battery/coordinate-divergence bug the bus was built to prevent).
- **Steps:** During a trip, watch the in-app driver position vs the published one over a long drive.
- **Pass:** One consistent position; no divergence between the trip-in-progress marker and the published location.

---

## B. Passenger app — watching a real bus

### B1 — Sub-second live updates via Realtime `P1`  ⬜
- **Why field:** needs a **moving** driver and a **separate** passenger device/network.
- **Steps:** With a driver actively moving (Section A), open the passenger map and watch the bus.
- **Pass:** The bus marker updates near real-time (sub-second deltas), not only every 30 s. (If only 30 s, see **C1** — publication not deployed.)

### B2 — ETA accuracy vs actual arrival `P1`  ⬜
- **Why field:** the whole point — predicted vs reality over a real trip.
- **Steps:** As a passenger, record the shown ETA at several points along the trip; record the **actual** arrival time at the terminal/stop.
- **Pass / record:** Log ETA error (predicted − actual) at each checkpoint. Define an acceptable tolerance (e.g. ± a few minutes) and note whether it holds.

### B3 — Dead-zone / offline ETA mode (dead reckoning) `P1`  ⬜
- **Why field:** the bus must actually enter a **real dead zone** on the route.
- **Steps:** Track a bus as it passes through a known no-signal stretch.
- **Pass:** The confidence pill degrades correctly with data age — **High → Medium → Low → Very Low (Predicted, "Offline ETA Mode", orange) → Outdated ("Bus may be in dead zone", red)** — and the detail screen shows the `DeadZoneBanner` with the last-update age. ETA keeps updating via offline dead reckoning, then recovers when the bus reappears.

### B4 — Passenger device offline vs vehicle dead zone `P2`  ⬜
- **Why field:** distinct cause from B3 — the passenger's own connection drops.
- **Steps:** Turn off the passenger phone's data while a trip is active.
- **Pass:** The `OfflineBanner` (passenger offline) shows — **not** the dead-zone banner — and cached vehicle data (`CachedPassengerVehicleEntity`) is used; it recovers on reconnect.

### B5 — Realtime reconnect across network transitions `P2`  ⬜
- **Steps:** As a passenger, switch Wi-Fi ↔ cellular, walk through patchy coverage, lock/unlock, during a live trip.
- **Pass:** Realtime resubscribes automatically; the map recovers without a manual refresh; the 30 s metadata poll continues meanwhile.

---

## C. Realtime & connectivity (cross-cutting)

### C1 — Realtime publication actually delivering `P1`  ⬜
- **Why field:** confirms `vehicle_live_location` is in the `supabase_realtime` publication and deltas arrive over the real network.
- **Steps:** With a moving bus, compare update cadence to the 30 s poll fallback. (Verify in SQL: `select … from pg_publication_tables where pubname='supabase_realtime' and tablename='vehicle_live_location';`)
- **Pass:** Sub-second deltas observed. If not, deploy migration `20260430_008_realtime_publication.sql`.

### C2 — Multi-device / multi-passenger concurrency `P2`  ⬜
- **Steps:** Several passengers watch the same bus (and multiple buses running) simultaneously.
- **Pass:** All devices see consistent positions/ETAs; no missed updates; server load acceptable.

### C3 — GPS accuracy in Samar terrain `P2`  ⬜
- **Why field:** mountainous / tree-covered stretches degrade GPS; only observable on-site.
- **Steps:** Note positions in known tricky spots (valleys, dense cover).
- **Pass / record:** Log where accuracy drops; confirm it doesn't produce wild jumps that break ETA/snap.

---

## D. Web Admin — features that need real trip data

These are built and desk-verified, but only become **meaningful/testable** once real trips
with GPS movement exist in the backend.

### D1 — "Generate polyline from a recorded trip" (#3B) `P1`  ⬜
- **Why field:** currently dark — the only trip with GPS points in the DB is a stationary QA trip (0 km), which is correctly filtered out. Needs a real **moving** completed trip.
- **Steps:** After a real trip (Section A) completes, open that route's **map editor** → the **"Generate from a recorded trip"** button should appear → click it.
- **Pass:** The button loads the real driven trace as the polyline; the shown **distance & duration come from the real trip**; Save persists it and (via the checkbox) updates the route's distance/duration.

### D2 — Duration-from-trips accuracy (#3A) `P1`  🟡
- **Why field:** the average is only as good as the accumulated real trips; the current sample is tiny (3 short trips → ~16 min).
- **Steps:** Run **several** real trips on a route over time, then open the route's **Edit** form and check the "Real average from N recent trips" suggestion.
- **Pass:** The suggested duration converges on the true typical travel time; "Use this" applies it. (Desk-verified that the math and degenerate-trip exclusion work; field-verify the *number* is realistic.)

### D3 — OSRM auto-route vs the actual bus road `P1`  ⬜
- **Why field:** OSRM picks the *car-fastest* road, which may differ from the road the bus actually takes.
- **Steps:** For each real route, **Generate route along roads**, then compare the generated line to the actual driven trace (or drive it and eyeball). Where it diverges, add **via-points** on the correct road and regenerate.
- **Pass:** The stored polyline matches the real bus road end-to-end.

### D4 — Terminal coordinate accuracy `P2`  ⬜
- **Why field:** verify each terminal pin matches the **physical** terminal on the ground.
- **Steps:** At each terminal, compare the phone's GPS location to the pin set via the map picker.
- **Pass:** Pin sits on the real terminal (within a sensible margin).

### D5 — Reports with real evidence from the field `P2`  ⬜
- **Steps:** A passenger submits a report **with a photo/video** attachment from the field; an admin triages it in the web console.
- **Pass:** The report + evidence arrive and are viewable. *(Note: surfacing evidence attachments in the web admin Reports detail is a pending enhancement.)*

---

## E. Performance, battery & device matrix

### E1 — Battery drain over a full trip `P1`  ⬜
- **Why field:** only measurable on a real, full-length route with the foreground service running.
- **Steps:** Full charge → run a complete Allen ↔ Calbayog (or ↔ Catarman) trip with tracking on → record battery % start/end and elapsed time.
- **Pass / record:** Drain per hour is within an acceptable threshold for a driver's shift; phone doesn't overheat/throttle.

### E2 — Long-run stability `P2`  ⬜
- **Steps:** Keep a trip running for a multi-hour shift.
- **Pass:** No crashes, no memory growth that kills the service; positions publish the whole time.

### E3 — Device / OS matrix `P2`  ⬜
- **Steps:** Repeat core flows on a range of real devices (min SDK 24 → latest), including low-end phones drivers actually use.
- **Pass:** Foreground service, GPS, and Google Sign-In work across the matrix.

---

## F. Security & session (field-relevant)

### F1 — Session expiry during a long trip `P1`  ⬜
- **Why field:** the GoTrue token lifetime can elapse mid-shift.
- **Steps:** Run a trip long enough for the session to expire; observe driver tracking + the app.
- **Pass / record:** Document behavior — does tracking survive/re-auth, or does `SessionExpiryNotifier` bounce to login mid-trip? (Web admin has **no** token refresh yet either.)

### F2 — Google Sign-In across real devices `P2`  🟡
- **Steps:** Sign in on several real devices/accounts (passenger and promoted-driver).
- **Pass:** Native Google auth works; a promoted driver lands on the Driver home after a fresh sign-in.

---

## Results log

| Date | Item | Tester | Device / route | Result | Notes (numbers, defects) |
|---|---|---|---|---|---|
|  |  |  |  |  |  |
|  |  |  |  |  |  |
|  |  |  |  |  |  |

**Sign-off:** _________________  **Date:** __________

---

_Created 2026-07-05. Keep this in sync as items are field-tested; move a passing item's
evidence (numbers, screenshots) into the results log so the capstone defense has proof._
