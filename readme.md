## Snack Shacks

### Database

```bash
php artisan migrate:fresh
php artisan db:seed --force
```

### Run below command in case of directory error

```bash
mkdir -p storage/framework/views
mkdir -p storage/framework/sessions
```

### Databse backup to Dropbox
```bash
php artisan db:backup
```

### Dropbox Setup

1. Create an app in the Dropbox developer console.
    Link: [Dropbox Developer Console](https://www.dropbox.com/developers)

2. How to create an app in Dropbox
    ![Create app](image.png)
        - Select "Scoped app", then "Full Dropbox", and enter an app name.
        - Go to the "Permissions" tab and select the `files.content.write` permission, then submit.
        ![File permission](image-1.png)
        - Create a folder named "database-backups" in your Dropbox home page.