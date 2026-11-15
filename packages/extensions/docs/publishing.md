# Publishing an extension

The [AdzerCMS extension catalog](https://adzercms.com/extensions) lists modules, integrations, themes, site templates and mini-apps by any author. Every AdzerCMS site shows it in the Extensions section of its admin panel and installs extensions from it with Composer.

## Prepare the package

An extension is a Composer package of the `adzercms-module` type in a git repository: a public one for a free extension, a private one will do for a paid extension. Start it from the module skeleton and describe it in `composer.json` as [Writing a module](https://github.com/adzercms/adzercms/blob/main/docs/module-development.md#package) shows.

The catalog row takes everything else from the package: the title and the description in the reader's language, the categories, the version, the `homepage` link and the source repository. The version is the latest stable one, or the default branch while there is no stable version.

## Check it

The skeleton's CI runs the [module check](https://github.com/adzercms/adzercms/blob/main/docs/module-development.md#module-check) on every push. Fix whatever it rejects before you publish: the engine runs the same check on every published version, and sites ask for confirmation before installing a version without the verified mark.

## Publish it on Packagist

1. Submit the repository at [packagist.org](https://packagist.org/packages/submit) under the package name from `composer.json`.
2. Let Packagist pick up new pushes: [how to update packages](https://packagist.org/about#how-to-update-packages).
3. Release a version with a git tag such as `1.0.0` and push the tag. Leave `version` out of `composer.json`: Composer takes it from the tag.

## Or sell it on OPOTOPO

A paid extension is sold by your listing on [OPOTOPO](https://opotopo.com): buyers get the package from the Composer repository of OPOTOPO, so it stays off Packagist.

1. Create a listing in Digital goods → Extensions: modules and plugins and name its package: Composer and the package name from `composer.json`. The vendor part of the name stays yours on OPOTOPO after the first package.
2. Connect the git repository of the package to the listing. OPOTOPO shows a deploy key and a webhook address: add the key to the deploy keys of the repository with read access, and the webhook for push events. Every tag with a version number such as `1.0.0` becomes a version.
3. Set the price in the variants of the listing and publish it.

The catalog shows the price and leads to the purchase on your listing. Once the catalog entry is published, the listing joins the public AdzerCMS collection on OPOTOPO, and AdzerCMS sites offer the extension in the Extensions section: the site admin buys it on OPOTOPO and installs it after connecting the OPOTOPO account on the Extensions page of the settings.

## Add your author key

The catalog accepts only a package that carries the author key of the person submitting it.

1. Sign in at [adzercms.com](https://adzercms.com) and open [My extensions](https://adzercms.com/extensions/mine). Your key is there as a line for `composer.json`.
2. Put the key into `extra.adzercms` next to the bundle, the title and the description:

   ```json
   "extra": {
       "adzercms": {
           "bundle": "Acme\\Pigeon\\PigeonBundle",
           "title": {"en": "Pigeon SMS", "ru": "SMS голубями"},
           "description": {"ru": "SMS голубиной почтой"},
           "author": "<your key>"
       }
   }
   ```

3. Release a new version with the key.

If a later version drops the key, the entry leaves the catalog.

## Add the entry

In My extensions, press the + Extension button and fill in the package name, the type and, if you have one, the address of a demo site. Add screenshots as JPEG, PNG or WebP images: the first one is the cover of the row, dragging changes the order. The rest comes from the package: the catalog refreshes it once a day and whenever you save the entry.

A new entry waits for the catalog operator. You get a notice when it is published or rejected, and a rejection comes with the reason, which My extensions shows next to the entry as well.

- Changes to a published entry go live at once
- Saving a rejected or withdrawn entry sends it back for review

## Release new versions

Tag and push a new version. The catalog shows it after its next refresh.
