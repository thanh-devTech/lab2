#!/usr/bin/env bash
#
# Installs/refreshes the WordPress core for this site. Core is NOT committed to the repo
# (see .gitignore + DEPENDENCIES.md); this fetches the pinned version into the repo root,
# leaving wp-config.php and wp-content/ untouched.
#
# Run after cloning and as part of deploy. To upgrade WordPress, bump WP_VERSION, run, test.
set -euo pipefail

WP_VERSION="6.8.5"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "Installing WordPress core ${WP_VERSION} into ${ROOT} ..."

if command -v wp >/dev/null 2>&1; then
	wp core download --version="${WP_VERSION}" --skip-content --force --path="${ROOT}"
else
	TMP="$(mktemp -d)"
	trap 'rm -rf "${TMP}"' EXIT
	curl -fsSL -o "${TMP}/wp.zip" "https://wordpress.org/wordpress-${WP_VERSION}-no-content.zip"
	unzip -q "${TMP}/wp.zip" -d "${TMP}"
	rm -rf "${ROOT}/wp-admin" "${ROOT}/wp-includes"
	cp -R "${TMP}/wordpress/wp-admin" "${ROOT}/wp-admin"
	cp -R "${TMP}/wordpress/wp-includes" "${ROOT}/wp-includes"
	for f in index.php xmlrpc.php readme.html license.txt \
		wp-activate.php wp-blog-header.php wp-comments-post.php wp-config-sample.php \
		wp-cron.php wp-links-opml.php wp-load.php wp-login.php wp-mail.php \
		wp-settings.php wp-signup.php wp-trackback.php; do
		cp "${TMP}/wordpress/${f}" "${ROOT}/${f}"
	done
fi

echo "Done. WordPress core ${WP_VERSION} installed; wp-config.php and wp-content/ untouched."
