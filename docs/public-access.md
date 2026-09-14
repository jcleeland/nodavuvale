# Public ancestors

Deploy the code, run **Administration → Database Migrations**, then open
**Administration → Public Ancestor Access**. Public access is initially disabled.
Review **Preview the public directory**, set individual exclusions, verify private
file protection below, then enable access. The default threshold is 50 years and
the initial site timezone is Australia/Sydney. Both are editable by administrators.

`index.php?to=public/ancestors` provides name search and pagination.
`index.php?to=public/individual&individual_id=123` provides a public profile.
Existing family/individual links select this view for guests. Approved members
keep the member view. Only administrators can use `preview=1` while access is off.
Previews exclude the same individuals and fields as the public view.

Public records show names, date qualifiers, birth/death dates, and independently
eligible relatives. Unknown, invalid, about, and after death dates remain private.
Incomplete dates use the end of the supplied period, including for before dates
(a deliberately conservative bound). Exactly N years since that bound is still
private; the following day qualifies. February 29 anniversaries clamp to February
28 in non-leap years. A deceased checkbox alone never qualifies a record.

Exclusions override the date rule, including in search and relatives. Siblings
are linked only through an eligible parent. Ancestor access does not publish stories,
events, photos, documents, user accounts, feeds, the full tree, or PDF reports. No sitemap
or public export is added. Public pages send no-store headers; do not override
these in a reverse proxy or CDN. Already downloaded copies cannot be recalled.

## Protect direct file URLs before enabling public access

Private uploads already have direct `/uploads/` URLs. Application page permissions
alone cannot protect files served directly by nginx/Apache. The production server
configuration is not in this repository; apply and verify this server change as
part of rollout. The new `private_file.php` serves existing upload URLs only to
approved members, without changing stored file paths or PDF filesystem access.

For nginx serving this site at the domain root, add this location to the same
server block as the application's existing PHP location, adapting `fastcgi_pass`
to exactly the upstream already used by that location:

```nginx
location ^~ /uploads/ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /srv/solischildren/app/private_file.php;
    fastcgi_param SCRIPT_NAME /private_file.php;
    fastcgi_pass php:9000; # example: use the existing PHP upstream
}
```

Ensure the normal FastCGI params forward the original REQUEST_URI and cookies.
Do not serve `/system/`, `/tests/`, `/docs/`, `/views/`, configuration files, or SQL
dumps over HTTP. Do not allow arbitrary uploaded PHP to execute. For Apache,
route upload requests to the same controller with a server rewrite preserving the
original request URI. The file controller currently assumes a domain-root install.

Test with a real existing image and document URL: anonymous GET/HEAD must return
403; an approved member must still see/download it. Clear old proxy/CDN caches for
upload URLs. Recheck PDFs, avatars, and editor images as a member. Do not enable
public access until these checks pass. This repository change does not modify the
production nginx configuration or enable public access remotely.

## Public discussion articles

After deploying, run **Administration → Database Migrations** to apply
`20260914_003_public_discussions`. Existing discussions remain private. Article
publication is independent of the public ancestor settings and age threshold.

In **News & Discussions**, an administrator can expand **Public visibility** on
an article, review the privacy warning, confirm review of its text and images,
and click **Publish publicly**. The **Public article** link opens
`index.php?to=public/discussion&discussion_id=123`. **Make private** withdraws it.
These controls change visibility only; editing continues through the existing
member tools. All subsequent edits and added images are public immediately,
without another approval step. Comments, reactions, author accounts and document
attachments are not part of the public article.

The logged-out homepage lists published articles in a full-width **Family stories**
section beneath the existing visitor cards. It uses current titles and omits the
section when no articles are public. The article uses the standard hero and card
styling, and a restricted HTML renderer retains complete inline `style` attributes
(including image layout and spacing) while removing scripts, forms, event handlers
and embedded active HTML. Authored CSS is preserved as approved, including CSS URLs;
it is not restricted to a property allowlist. The privacy review includes that styling.

Inline uploaded images and image attachments are served through
`public_discussion_image.php`, which rechecks the current article and actual image
file type on every request. Removing an image reference (unless it remains attached
to that article), unpublishing, or deleting the discussion revokes that endpoint's
access. Images on external websites and embedded raster data images are displayed
as supplied. The existing `/uploads/` member-only server rule above stays in place;
do not make the uploads directory public. No additional web-server rewrite is
needed for the image endpoint. Public pages, listings and images send no-store
headers. This cannot revoke copies someone has already downloaded.

Run `php tests/public_article_html_test.php` for renderer checks. The integration
test below also covers publication permissions and CSRF, the privacy acknowledgement,
public images, hidden comments and files, live edits, unpublishing, and incomplete
migrations. These tests use only disposable data and do not publish real articles.

## Verification

Run `php tests/public_access_test.php` and the existing PHP tests. The opt-in
database integration test documented in its source uses only a disposable test
database, never the production configuration. Verify the admin controls and guest
routes on staging, including forged POSTs, private IDs in title/query parameters,
relationships to living relatives, immediate exclusion, and date corrections.

To run the integration test against a disposable local MariaDB/MySQL server:

```sh
NV_TEST_MYSQL_DSN='mysql:host=127.0.0.1;port=33077' \
NV_TEST_MYSQL_USER=root NV_TEST_MYSQL_PASSWORD='' \
php tests/public_access_integration_test.php
```

This test requires PDO MySQL, mbstring, cURL, proc_open, and permission to create
and drop a randomly named test database. It also runs a temporary PHP HTTP server
on loopback to test the real routes, using a disposable copy of the application.
