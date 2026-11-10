# Rocket Panel

SSH panel (Rocket SSH based) with an agency/reseller system, wallet ledger, plans, keep-oldest-connection rule and one-click updates.

## Install / update / manage

Run as root (on the server):

    bash <(curl -Ls https://raw.githubusercontent.com/vtajook/rocket-panel/main/install.sh)

Menu: update panel · install · change panel login · uninstall · exit.

* **Update** replaces panel files only. Users, resellers, settings, `.env`, SSH and the tunnels are not touched; nobody is disconnected. The same update runs from the panel (side menu → «بروزرسانی پنل»).
* **Install** asks admin username/password, panel port, SSH port, UDPGW port. If a panel already exists, its users and settings are kept.
* **Change login** only changes the first admin's username/password.

## Rules
* Volume is not billed: keep per-GB price `0` (default) and leave plan GB empty.
* Extra devices beyond the limit are dropped; the oldest connection stays. Accounts are never disabled for this.
* Accounts missing on the server (after a restore) are created automatically every minute.
