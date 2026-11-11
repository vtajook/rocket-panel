#!/bin/bash
# Rocket Panel — install / update / change panel login / uninstall
#   bash <(curl -Ls https://raw.githubusercontent.com/vtajook/rocket-panel/main/install.sh)
# Non-interactive:  rocket-panel --update   (panel files only, nothing else is touched)

REPO="vtajook/rocket-panel"
BRANCH="main"
WEB="/var/www/html"
PANEL="$WEB/panel"
TOOL="/usr/local/sbin/rocket-panel"
SUDOERS="/etc/sudoers.d/rocket-panel"
DB_NAME="RocketSSH"
DB_PREFIX="cp_"

red()   { printf "\e[31m%s\e[0m\n" "$*"; }
green() { printf "\e[32m%s\e[0m\n" "$*"; }
yellow(){ printf "\e[33m%s\e[0m\n" "$*"; }

need_root() {
    if [ "$EUID" -ne 0 ]; then
        red "Run as root:  sudo bash $0"
        exit 1
    fi
}

env_get() { grep -E "^$1=" "$PANEL/.env" 2>/dev/null | head -1 | cut -d= -f2-; }
env_set() {
    local k="$1" v="$2"
    if grep -qE "^$k=" "$PANEL/.env"; then
        sed -i "s|^$k=.*|$k=$v|" "$PANEL/.env"
    else
        echo "$k=$v" >> "$PANEL/.env"
    fi
}

is_installed() { [ -f "$PANEL/.env" ] && [ -f "$PANEL/index.php" ]; }

valid_port() { [[ "$1" =~ ^[0-9]+$ ]] && [ "$1" -ge 1 ] && [ "$1" -le 65535 ]; }

ask() { # ask VAR "text" default
    local __v="$1" text="$2" def="$3" ans
    read -r -p "$text [$def]: " ans || exit 1
    printf -v "$__v" '%s' "${ans:-$def}"
}

ask_port() { # ask_port VAR "text" default
    local __v="$1" ans
    while true; do
        ask ans "$2" "$3"
        if valid_port "$ans"; then printf -v "$__v" '%s' "$ans"; return; fi
        red "Invalid port"
    done
}

port_in_use_by_other() { # port, allowed process pattern
    ss -ltnp 2>/dev/null | awk -v p=":$1\$" '$4 ~ p' | grep -vqE "$2" && return 0
    return 1
}

# ---------------------------------------------------------------- code download
fetch_code() {
    TMP=$(mktemp -d)
    green "Downloading latest version from GitHub..."
    if ! curl -fsSL "https://codeload.github.com/$REPO/tar.gz/refs/heads/$BRANCH" -o "$TMP/src.tgz"; then
        red "Download failed"; rm -rf "$TMP"; return 1
    fi
    tar -xzf "$TMP/src.tgz" -C "$TMP" || { red "Archive is broken"; rm -rf "$TMP"; return 1; }
    SRC=$(find "$TMP" -maxdepth 1 -mindepth 1 -type d | head -1)
    if [ ! -f "$SRC/panel/index.php" ]; then red "Unexpected archive layout"; rm -rf "$TMP"; return 1; fi
    PHPBIN=$(command -v php7.4 || command -v php)
    if [ -n "$PHPBIN" ]; then
        local bad=0
        while IFS= read -r f; do
            "$PHPBIN" -l "$f" >/dev/null 2>&1 || { red "Syntax error in downloaded $f"; bad=1; }
        done < <(find "$SRC/panel/app" -name '*.php' | head -400)
        [ $bad -eq 1 ] && { rm -rf "$TMP"; return 1; }
    fi
    return 0
}

# copy code only: .env, storage, banner and everything the panel generated stay as they are
deploy_code() {
    mkdir -p "$PANEL" "$WEB/account" "$WEB/bot"
    (cd "$SRC/panel" && tar cf - --exclude=./.env --exclude=./.env.example --exclude=./storage --exclude=./banner.txt .) | (cd "$PANEL" && tar xf -)
    (cd "$SRC/account" && tar cf - .) | (cd "$WEB/account" && tar xf -)
    (cd "$SRC/bot" && tar cf - .) | (cd "$WEB/bot" && tar xf -)
    cp -f "$SRC/index.php" "$WEB/index.php" 2>/dev/null
    rm -rf "$WEB/decoy" "$PANEL/storage/fake_site.txt"   # the built-in decoy site was removed
    for d in cache debug logs sessions temp; do mkdir -p "$PANEL/storage/$d"; done
    [ -f "$PANEL/storage/.htaccess" ] || cp "$SRC/panel/storage/.htaccess" "$PANEL/storage/.htaccess" 2>/dev/null
    touch "$PANEL/banner.txt"
    [ -f "$PANEL/.env" ] || cp "$SRC/panel/.env.example" "$PANEL/.env"
    # force the module to re-check its tables on next request (new migrations)
    rm -f "$PANEL/storage/agency_schema.ver"
    chown -R www-data:www-data "$PANEL" "$WEB/account" "$WEB/bot" "$WEB/index.php" 2>/dev/null
    install -m 0755 "$SRC/install.sh" "$TOOL.new" && mv -f "$TOOL.new" "$TOOL"
}

# ---------------------------------------------------------------- system pieces
install_packages() {
    export DEBIAN_FRONTEND=noninteractive
    local need=0
    command -v php7.4 >/dev/null 2>&1 || need=1
    command -v apache2 >/dev/null 2>&1 || need=1
    command -v mysql >/dev/null 2>&1 || need=1
    if [ $need -eq 1 ]; then
        apt-get update -y
        apt-get install -y software-properties-common curl unzip net-tools lsof cron openssl
        add-apt-repository -y ppa:ondrej/php
        apt-get update -y
        apt-get install -y apache2 mariadb-server php7.4 php7.4-cli php7.4-mysql php7.4-xml php7.4-curl php7.4-mbstring libapache2-mod-php7.4
    fi
    for m in php7.4 rewrite; do a2enmod "$m" >/dev/null 2>&1; done
    grep -qx "/usr/sbin/nologin" /etc/shells || echo "/usr/sbin/nologin" >> /etc/shells
    grep -qx "/bin/false" /etc/shells || echo "/bin/false" >> /etc/shells
    systemctl enable --now mariadb >/dev/null 2>&1
    systemctl enable --now cron >/dev/null 2>&1
}

configure_sudoers() {
    cat > "$SUDOERS.new" <<'EOF'
www-data ALL=(ALL:ALL) NOPASSWD: /usr/sbin/adduser, /usr/sbin/userdel, /usr/bin/passwd
www-data ALL=(ALL:ALL) NOPASSWD: /usr/bin/kill, /usr/bin/killall, /usr/bin/pkill, /usr/bin/pgrep, /usr/bin/timeout
www-data ALL=(ALL:ALL) NOPASSWD: /usr/bin/lsof, /usr/sbin/lsof, /usr/bin/ss, /usr/bin/netstat
www-data ALL=(ALL:ALL) NOPASSWD: /usr/bin/sed, /usr/bin/rm, /usr/bin/mysqldump
www-data ALL=(ALL:ALL) NOPASSWD: /usr/bin/systemctl restart sshd, /usr/bin/systemctl restart ssh, /usr/bin/systemctl daemon-reload, /usr/bin/systemctl restart videocall, /usr/bin/systemctl reboot
www-data ALL=(ALL:ALL) NOPASSWD: /usr/local/sbin/rocket-panel --update
EOF
    chmod 440 "$SUDOERS.new"
    if visudo -cf "$SUDOERS.new" >/dev/null 2>&1; then
        mv -f "$SUDOERS.new" "$SUDOERS"
    else
        red "sudoers check failed; leaving the existing sudo rules untouched"
        rm -f "$SUDOERS.new"
    fi
}

configure_apache() { # $1 = new panel port, $2 = old port (may be empty)
    local port="$1" old="$2" conf
    # panel already served by an existing vhost? only move the port if it changed
    conf=$(grep -rlE "DocumentRoot[[:space:]]+$PANEL" /etc/apache2/sites-enabled/ 2>/dev/null | head -1)
    if [ -n "$conf" ]; then
        if [ -n "$old" ] && [ "$old" != "$port" ]; then
            cp -a /etc/apache2/ports.conf /etc/apache2/ports.conf.rocket.bak
            cp -a "$(readlink -f "$conf")" "$(readlink -f "$conf").rocket.bak"
            sed -i -E "s/\*:$old\b/*:$port/g" "$(readlink -f "$conf")"
            sed -i -E "s/^Listen[[:space:]]+$old\$/Listen $port/" /etc/apache2/ports.conf
        fi
        grep -qE "^Listen[[:space:]]+$port\$" /etc/apache2/ports.conf || echo "Listen $port" >> /etc/apache2/ports.conf
    else
        cat > /etc/apache2/sites-available/rocket-panel.conf <<EOF
<VirtualHost *:$port>
    ServerAdmin webmaster@localhost
    DocumentRoot $PANEL
    ErrorLog \${APACHE_LOG_DIR}/rocket-error.log
    CustomLog \${APACHE_LOG_DIR}/rocket-access.log combined
    <Directory $PANEL>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF
        grep -qE "^Listen[[:space:]]+$port\$" /etc/apache2/ports.conf || echo "Listen $port" >> /etc/apache2/ports.conf
        a2ensite rocket-panel >/dev/null 2>&1
    fi
    if apache2ctl configtest >/dev/null 2>&1; then
        systemctl reload apache2 2>/dev/null || systemctl restart apache2
    else
        red "Apache config test failed:"; apache2ctl configtest
        [ -f /etc/apache2/ports.conf.rocket.bak ] && cp -f /etc/apache2/ports.conf.rocket.bak /etc/apache2/ports.conf
        return 1
    fi
}

configure_ssh_port() { # $1 = wanted port
    local want="$1" cur
    cur=$(grep -E "^[[:space:]]*Port[[:space:]]+[0-9]+" /etc/ssh/sshd_config | head -1 | awk '{print $2}')
    cur=${cur:-22}
    if [ "$cur" = "$want" ]; then
        echo "SSH port is already $want (sshd not touched)"
        return 0
    fi
    yellow "Changing SSH port $cur -> $want (existing connections stay up)"
    cp -a /etc/ssh/sshd_config "/etc/ssh/sshd_config.rocket.$(date +%s).bak"
    if grep -qE "^[[:space:]]*Port[[:space:]]+[0-9]+" /etc/ssh/sshd_config; then
        sed -i -E "0,/^[[:space:]]*Port[[:space:]]+[0-9]+/s//Port $want/" /etc/ssh/sshd_config
    else
        echo "Port $want" >> /etc/ssh/sshd_config
    fi
    if sshd -t; then
        systemctl reload ssh 2>/dev/null || systemctl reload sshd 2>/dev/null
        sleep 1
        ss -ltn | grep -q ":$want " || yellow "sshd is not listening on $want yet. On Ubuntu 24.04 you may need: systemctl disable --now ssh.socket && systemctl restart ssh"
    else
        red "sshd config test failed, restoring"
        cp -f "$(ls -t /etc/ssh/sshd_config.rocket.*.bak | head -1)" /etc/ssh/sshd_config
    fi
}

install_udpgw() { # $1 = port
    local port="$1" unit=/etc/systemd/system/videocall.service
    if [ ! -x /usr/local/bin/badvpn-udpgw ]; then
        apt-get install -y git cmake make gcc >/dev/null 2>&1
        rm -rf /tmp/badvpn && git clone --depth 1 https://github.com/ambrop72/badvpn.git /tmp/badvpn \
            && mkdir -p /tmp/badvpn/build && cd /tmp/badvpn/build \
            && cmake .. -DBUILD_NOTHING_BY_DEFAULT=1 -DBUILD_UDPGW=1 >/dev/null \
            && make >/dev/null && cp udpgw/badvpn-udpgw /usr/local/bin/ ; cd /
    fi
    id videocall >/dev/null 2>&1 || useradd -m videocall
    if [ -f "$unit" ] && grep -q "127.0.0.1:$port " "$unit"; then
        echo "UDPGW already on $port (not restarted)"
        systemctl is-active --quiet videocall || systemctl start videocall
        return 0
    fi
    cat > "$unit" <<EOF
[Unit]
Description=UDP forwarding for badvpn-tun2socks
After=nss-lookup.target

[Service]
ExecStart=/usr/local/bin/badvpn-udpgw --loglevel none --listen-addr 127.0.0.1:$port --max-clients 999
User=videocall

[Install]
WantedBy=multi-user.target
EOF
    systemctl daemon-reload
    systemctl enable videocall >/dev/null 2>&1
    systemctl restart videocall
}

php_hash() { PW="$1" php -r 'echo password_hash(getenv("PW"), PASSWORD_BCRYPT);'; }

# run SQL given on stdin with the panel's own database login
db_run() {
    local u p
    u=$(env_get DB_USERNAME); p=$(env_get DB_PASSWORD)
    MYSQL_PWD="$p" mysql -u"$u" "$DB_NAME"
}

setup_database() { # $1 admin user, $2 admin pass, $3 ssh port, $4 udp port
    local au="$1" ap="$2" sp="$3" up="$4" dbpass hash now
    hash=$(php_hash "$ap"); now=$(date +%s)
    if [ -z "$(env_get DB_USERNAME)" ] || [ "$(env_get DB_USERNAME)" = "root" ] || ! echo "select 1" | db_run >/dev/null 2>&1; then
        # first install (or the .env has no working login): create a database and a limited user
        dbpass=$(openssl rand -hex 12)
        mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4;"
        mysql -e "CREATE USER IF NOT EXISTS 'rocket_user'@'localhost' IDENTIFIED BY '$dbpass';"
        mysql -e "ALTER USER 'rocket_user'@'localhost' IDENTIFIED BY '$dbpass';"
        mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO 'rocket_user'@'localhost'; FLUSH PRIVILEGES;"
        env_set DB_HOST localhost
        env_set DB_DATABASE "$DB_NAME"
        env_set DB_USERNAME rocket_user
        env_set DB_PASSWORD "$dbpass"
    fi
    DB_NAME=$(env_get DB_DATABASE)
    local has
    has=$(echo "SHOW TABLES LIKE '${DB_PREFIX}admins'" | db_run | wc -l)
    if [ "$has" -eq 0 ]; then
        echo "Creating tables..."
        db_run < "$PANEL/assets/backup/db.sql"
        {
            echo "INSERT INTO ${DB_PREFIX}admins (username,password,fullname,role,credit,is_active,ctime,utime) VALUES ('$au','$hash','modir','admin','0','1','$now','0');"
            echo "INSERT INTO ${DB_PREFIX}settings (name,value) VALUES ('ssh_port','$sp'),('udp_port','$up'),('app_version','2.0'),('calc_traffic','1');"
        } | db_run
    else
        echo "Existing database found: users and settings are kept."
        {
            echo "UPDATE ${DB_PREFIX}settings SET value='$sp' WHERE name='ssh_port';"
            echo "UPDATE ${DB_PREFIX}settings SET value='$up' WHERE name='udp_port';"
        } | db_run
        if [ -n "$au" ]; then set_admin_login "$au" "$ap"; fi
    fi
}

set_admin_login() { # $1 user $2 pass  (first admin only; resellers are never touched)
    local hash id
    hash=$(php_hash "$2")
    id=$(echo "SELECT id FROM ${DB_PREFIX}admins WHERE role='admin' ORDER BY id LIMIT 1" | db_run | tail -1)
    [ -z "$id" ] && { red "No admin account found"; return 1; }
    echo "UPDATE ${DB_PREFIX}admins SET username='$1', password='$hash' WHERE id=$id;" | db_run
    # sign out every open admin session of this account if the module knows about it
    echo "UPDATE ${DB_PREFIX}admins SET token_ver=token_ver+1 WHERE id=$id;" | db_run 2>/dev/null
}

configure_cron() { # $1 panel port
    local port="$1" f="$WEB/cronjob.sh"
    # the loop script is rewritten only when missing or pointing to another port
    if [ ! -f "$f" ] || ! grep -q "127.0.0.1:$port/cron/master" "$f" || ! grep -q "rocket-cron-v2" "$f"; then
        cat > "$f" <<EOF
#!/bin/bash
# rocket-cron-v2: one run at a time (a slow run can no longer pile up parallel requests)
curlUrl="http://127.0.0.1:$port/cron/master"
lockfile="/tmp/call_url.lock"
if [ -e "\$lockfile" ] && kill -0 "\$(cat \$lockfile 2>/dev/null)" 2>/dev/null; then
    exit 1
fi
echo \$\$ > "\$lockfile"
trap 'rm -f "\$lockfile"' EXIT
while true; do
    curl -s -m 25 -o /dev/null "\$curlUrl"
    sleep 3
done
EOF
        chmod +x "$f"
        pkill -f "$f" 2>/dev/null
        rm -f /tmp/call_url.lock
    fi
    (crontab -l 2>/dev/null | grep -v "cronjob.sh"; echo "* * * * * $f") | crontab -
}

# ---------------------------------------------------------------- speed / memory
configure_perf() {
    # PHP opcache (compiled code stays in memory) -- the default is small and re-checks files every 2s
    local d
    for d in /etc/php/7.4/apache2/conf.d /etc/php/7.4/cli/conf.d; do
        [ -d "$d" ] || continue
        cat > "$d/99-rocket.ini" <<'EOF'
opcache.enable=1
opcache.memory_consumption=96
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.validate_timestamps=1
opcache.revalidate_freq=30
realpath_cache_size=1M
realpath_cache_ttl=300
EOF
    done
    # opcache is useless for the one-shot CLI
    [ -f /etc/php/7.4/cli/conf.d/99-rocket.ini ] && sed -i 's/^opcache.enable=1/opcache.enable=0/' /etc/php/7.4/cli/conf.d/99-rocket.ini

    # Apache: compress + cache the panel's static files, keep idle connections short, cap workers
    a2enmod deflate expires headers >/dev/null 2>&1
    cat > /etc/apache2/conf-available/rocket-perf.conf <<'EOF'
KeepAlive On
KeepAliveTimeout 3
MaxKeepAliveRequests 200
Timeout 60
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript text/javascript application/json image/svg+xml
</IfModule>
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 7 days"
    ExpiresByType application/javascript "access plus 7 days"
    ExpiresByType text/javascript "access plus 7 days"
    ExpiresByType image/png "access plus 30 days"
    ExpiresByType image/svg+xml "access plus 30 days"
    ExpiresByType font/woff2 "access plus 30 days"
    ExpiresByType application/font-woff2 "access plus 30 days"
</IfModule>
<IfModule mpm_prefork_module>
    StartServers 2
    MinSpareServers 2
    MaxSpareServers 6
    MaxRequestWorkers 60
    MaxConnectionsPerChild 1000
</IfModule>
EOF
    a2enconf rocket-perf >/dev/null 2>&1
    if apache2ctl configtest >/dev/null 2>&1; then
        systemctl reload apache2 2>/dev/null || true     # graceful: running requests finish, SSH users untouched
    else
        a2disconf rocket-perf >/dev/null 2>&1
        rm -f /etc/apache2/conf-available/rocket-perf.conf
    fi
}

# ---------------------------------------------------------------- stability defaults
configure_tuning() {
    # MariaDB: many short panel/cron requests must never hit "Too many connections"
    mkdir -p /etc/mysql/mariadb.conf.d
    cat > /etc/mysql/mariadb.conf.d/99-rocket.cnf <<'EOF'
[mysqld]
max_connections = 500
wait_timeout = 120
interactive_timeout = 120
EOF
    mysql -e "SET GLOBAL max_connections=500; SET GLOBAL wait_timeout=120; SET GLOBAL interactive_timeout=120;" 2>/dev/null

    # BBR
    if ! grep -q "rocket-bbr" /etc/sysctl.conf 2>/dev/null; then
        printf "\n# rocket-bbr\nnet.core.default_qdisc=fq\nnet.ipv4.tcp_congestion_control=bbr\n" >> /etc/sysctl.conf
        sysctl -p >/dev/null 2>&1
    fi

    # sshd: survive reconnect storms and free dead sessions quickly (reload keeps current connections)
    if ! grep -q "^# rocket-ssh-tuning" /etc/ssh/sshd_config; then
        cp -a /etc/ssh/sshd_config /etc/ssh/sshd_config.rocket-tuning.bak
        sed -i -E '/^[[:space:]]*(MaxStartups|LoginGraceTime|ClientAliveInterval|ClientAliveCountMax)[[:space:]]/d' /etc/ssh/sshd_config
        { printf "# rocket-ssh-tuning\nMaxStartups 2000:30:4000\nLoginGraceTime 20\nClientAliveInterval 15\nClientAliveCountMax 3\n\n"; cat /etc/ssh/sshd_config; } > /tmp/sshd_new
        cat /tmp/sshd_new > /etc/ssh/sshd_config
        rm -f /tmp/sshd_new
        if sshd -t; then
            systemctl reload ssh 2>/dev/null || systemctl reload sshd 2>/dev/null
        else
            red "sshd test failed, restoring"
            cp -f /etc/ssh/sshd_config.rocket-tuning.bak /etc/ssh/sshd_config
        fi
    fi

    # watchdog: restart MariaDB / Apache only when they really stopped answering
    local pp; pp=$(env_get PORT_PANEL)
    cat > /usr/local/sbin/rocket-watchdog <<EOF
#!/bin/bash
mysqladmin ping >/dev/null 2>&1 || systemctl restart mariadb
code=\$(curl -s -m 15 -o /dev/null -w "%{http_code}" http://127.0.0.1:${pp:-8081}/login)
case "\$code" in 2*|3*) ;; *) systemctl restart apache2 ;; esac
EOF
    chmod +x /usr/local/sbin/rocket-watchdog
    (crontab -l 2>/dev/null | grep -v rocket-watchdog; echo "*/2 * * * * /usr/local/sbin/rocket-watchdog") | crontab -
}

# ---------------------------------------------------------------- actions
do_install() {
    need_root
    local reinstall=0 au ap sp up pp oldpp curssh
    is_installed && reinstall=1
    echo
    if [ $reinstall -eq 1 ]; then
        yellow "An existing panel was found: users and settings will be kept."
        oldpp=$(env_get PORT_PANEL)
        au=""; ap=""
    else
        green "=== Rocket Panel installation ==="
    fi
    curssh=$(grep -E "^[[:space:]]*Port[[:space:]]+[0-9]+" /etc/ssh/sshd_config | head -1 | awk '{print $2}')
    if [ $reinstall -eq 1 ]; then
        read -r -p "Panel admin username (blank = keep current): " au
        if [ -n "$au" ]; then read -r -p "Panel admin password: " ap; [ -z "$ap" ] && { red "Password required"; return 1; }; fi
    else
        ask au "Panel admin username" "admin"
        while true; do read -r -p "Panel admin password: " ap || exit 1; [ ${#ap} -ge 4 ] && break; red "At least 4 characters"; done
    fi
    [[ "$au" =~ ^[A-Za-z0-9_.-]*$ ]] || { red "Username: letters, digits, _ . - only"; return 1; }
    [[ "$ap" != *"'"* && "$ap" != *'\'* ]] || { red "Password must not contain ' or \\"; return 1; }
    ask_port pp "Panel port" "${oldpp:-8081}"
    ask_port sp "SSH port" "${curssh:-22}"
    ask_port up "UDPGW port" "$(env_get PORT_UDP | grep . || echo 7300)"
    [ "$pp" = "$sp" ] && { red "Panel port and SSH port must differ"; return 1; }
    if [ "$pp" != "$oldpp" ] && port_in_use_by_other "$pp" "apache2"; then red "Port $pp is already used by another program"; return 1; fi

    install_packages
    fetch_code || return 1
    deploy_code
    setup_database "$au" "$ap" "$sp" "$up"
    env_set PORT_SSH "$sp"; env_set PORT_UDP "$up"; env_set PORT_PANEL "$pp"
    configure_sudoers
    configure_apache "$pp" "$oldpp"
    configure_ssh_port "$sp"
    install_udpgw "$up"
    configure_cron "$pp"
    configure_tuning
    configure_perf
    timedatectl set-timezone Asia/Tehran >/dev/null 2>&1
    rm -rf "$TMP"
    curl -s -m 20 -o /dev/null "http://127.0.0.1:$pp/login"   # lets the panel run its migrations
    local ip; ip=$(curl -s -m 5 -4 ipv4.icanhazip.com)
    echo
    green "Done."
    echo "Panel : http://${ip:-SERVER_IP}:$pp/login"
    [ -n "$au" ] && echo "Login : $au"
    echo "SSH $sp   UDPGW $up"
}

# lets the panel's update button run the root updater (only this one command)
ensure_update_sudo() {
    local f=/etc/sudoers.d/rocket-panel-update
    printf 'www-data ALL=(root) NOPASSWD: /usr/local/sbin/rocket-panel --update, /usr/bin/ss, /usr/sbin/ss\n' > "$f.new"
    chmod 440 "$f.new"
    if visudo -cf "$f.new" >/dev/null 2>&1; then mv -f "$f.new" "$f"; else rm -f "$f.new"; red "could not install the sudo rule for the update button"; fi
}

do_update() { # panel files only
    need_root
    is_installed || { red "Panel is not installed"; echo "UPDATE-FAILED"; return 1; }
    local ver_old; ver_old=$(cat "$PANEL/VERSION" 2>/dev/null)
    fetch_code || { echo "UPDATE-FAILED"; return 1; }
    deploy_code
    ensure_update_sudo
    chown -R www-data:www-data "$PANEL/storage"
    # only the web server is reloaded (graceful): SSH, tunnels and logged-in users are not touched
    local pp; pp=$(env_get PORT_PANEL)
    configure_cron "${pp:-8081}"      # rewrites the cron loop only when it is an older version
    configure_perf                    # opcache / Apache speed settings (graceful reload inside)
    systemctl reload apache2 2>/dev/null
    curl -s -m 20 -o /dev/null "http://127.0.0.1:${pp:-8081}/login"
    echo "version: ${ver_old:-?} -> $(cat "$PANEL/VERSION" 2>/dev/null)"
    rm -rf "$TMP"
    green "UPDATE-FINISHED"
}

do_change_login() {
    need_root
    is_installed || { red "Panel is not installed"; return 1; }
    local au ap
    read -r -p "New panel username: " au
    [[ "$au" =~ ^[A-Za-z0-9_.-]+$ ]] || { red "Username: letters, digits, _ . - only"; return 1; }
    read -r -p "New panel password: " ap
    [ ${#ap} -ge 4 ] || { red "At least 4 characters"; return 1; }
    [[ "$ap" != *"'"* && "$ap" != *'\'* ]] || { red "Password must not contain ' or \\"; return 1; }
    DB_NAME=$(env_get DB_DATABASE)
    set_admin_login "$au" "$ap" && green "Panel login changed. Nothing else was touched."
}

do_uninstall() {
    need_root
    is_installed || { red "Panel is not installed"; return 1; }
    red "This removes the panel files, web config, cron job and sudo rules."
    echo "SSH users already on the server are NOT deleted."
    read -r -p "Type YES to continue: " c
    [ "$c" = "YES" ] || { echo "Cancelled"; return 0; }
    read -r -p "Also delete the panel DATABASE (all accounts, resellers, settings)? type DELETE or leave blank: " d
    local pp; pp=$(env_get PORT_PANEL)
    [ -n "$pp" ] && sed -i "/^Listen $pp\$/d" /etc/apache2/ports.conf 2>/dev/null
    a2dissite rocket-panel >/dev/null 2>&1; rm -f /etc/apache2/sites-available/rocket-panel.conf
    pkill -f "$WEB/cronjob.sh" 2>/dev/null
    (crontab -l 2>/dev/null | grep -v "cronjob.sh") | crontab -
    rm -f "$WEB/cronjob.sh" /tmp/call_url.lock "$SUDOERS"
    if [ "$d" = "DELETE" ]; then
        mysql -e "DROP DATABASE IF EXISTS \`$(env_get DB_DATABASE)\`;"
        mysql -e "DROP USER IF EXISTS '$(env_get DB_USERNAME)'@'localhost';" 2>/dev/null
    fi
    rm -rf "$PANEL" "$WEB/account" "$WEB/bot"
    systemctl reload apache2 2>/dev/null
    green "Panel removed."
}

menu() {
    while true; do
        echo
        green "===== Rocket Panel ====="
        echo "1) Update panel (files only)"
        echo "2) Install"
        echo "3) Change panel username / password"
        echo "4) Uninstall"
        echo "0) Exit"
        read -r -p "Choose: " c || exit 0
        case "$c" in
            1) do_update ;;
            2) do_install ;;
            3) do_change_login ;;
            4) do_uninstall ;;
            0) exit 0 ;;
            *) red "Invalid choice" ;;
        esac
    done
}

case "$1" in
    --update) do_update ;;
    "") menu ;;
    *) echo "usage: $0 [--update]"; exit 1 ;;
esac
