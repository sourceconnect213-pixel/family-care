# Family Care Hospital, Kelaniya: website and doctor channelling

A dynamic website for Family Care Hospital, Kelaniya. Patients can:

- browse doctors by specialty
- see each doctor's weekly timetable and open dates for the next 30 days
- book an appointment number online, with an estimated time and the fees to pay at the counter
- check or cancel their booking using the reference number and their mobile number

Staff use an **admin panel** at `/admin` to:

- see today's sessions, how full each one is, and the latest bookings (dashboard)
- manage appointments: filter by date, doctor or status; search by name, phone, NIC or reference; mark patients Arrived, Completed, Cancelled or No show
- add walk-in and phone bookings (the front desk can still book after online booking closes)
- print the patient list for each session, or download appointments as a CSV file (opens in Excel)
- add and edit doctors (photo, specialty, qualifications, fee) and their weekly sessions (day, time, maximum patients, minutes per patient, room)
- mark a doctor on leave for a date range, for all sessions or one session; online booking closes for those dates
- edit specialties, hospital details (phone, address, opening hours, hospital charge, booking window) and staff accounts

The **public website** has a two-row header with large "Channel your doctor" and call buttons, a home page with a photo hero and the live channelling board, an A–Z services panel (medical and general services), a numbers strip, an About section, feature cards (Laboratory, Pharmacy), health tips and news with YouTube videos, a hotline band in the footer, and floating Emergency and Channelling buttons. All of it is edited in the admin panel under **Website**: home page text, photos and numbers; pages (About us, Laboratory, Pharmacy, Facilities, Careers, or new ones); health tips and news; and general services.

Each **doctor gets their own login** at `/portal`. There they see only their own patients: new bookings are highlighted, with a 14-day overview, each session's patient list (number, estimated time, age, phone, note) and their timetable and leave. They can mark patients Arrived, Completed or No show, and change their password. The admin sets up each doctor's username and password on the doctor's page in the admin panel.

**SMS messages** (Admin → SMS messages), sent through a Sri Lankan SMS gateway (Notify.lk or Text.lk) under the hospital's sender ID:

- the patient gets a confirmation (number, estimated time, reference) and a message if the booking is cancelled
- the hospital's official number gets an alert every time a doctor is channelled or a booking is cancelled; doctors' personal numbers are never used
- every message is logged with whether it was sent, and there is a "Send a test SMS" button

Colours come from the logo: navy blue `#0B2265` and red `#E30613`.

## Requirements

- PHP 8.1 or newer with `pdo_sqlite` (standard on most cPanel hosting) and `gd`
- Apache (the included `.htaccess` files protect the database) or any PHP-capable server

No database server is needed: data is stored in `data/family_care.sqlite`, created automatically on first visit. To use MySQL instead, set `DB_DRIVER` to `mysql` in `includes/config.php`, fill in the connection details and create an empty database. The tables are created for you.

## Put it online (cPanel)

1. Upload all files to `public_html` (or a sub-folder).
2. Make sure `data/` and `uploads/doctors/` are writable by PHP (permissions 755 or 775).
3. Open `https://your-domain/admin/` and create the first admin account. That setup page only works once.
4. In **Hospital settings**, replace the placeholder phone numbers, email, address and Google Maps link.
5. In **SMS messages**, choose the provider, enter the API details and sender ID from your SMS account, and the hospital's official number. Press "Send a test SMS".
6. In **Doctors & sessions**, replace the six sample doctors with the real doctors and their sessions. Delete or hide the samples.

## Run it on your computer

```bash
php -S localhost:8080
```

Then open http://localhost:8080, http://localhost:8080/admin and http://localhost:8080/portal.

## Online preview (github.io)

`tools/build-preview.php` builds a read-only copy of the whole site (public pages, admin panel and doctor portal) with sample data, as plain HTML. Nothing typed into the preview is saved. The GitHub Actions workflow `.github/workflows/preview.yml` publishes it to GitHub Pages on every push and once a day.

One-time setup: on GitHub open the repository's **Settings → Pages**, and under "Build and deployment" set **Source** to **GitHub Actions**. On a free GitHub account the repository must be public for Pages to work. The preview address will be `https://<github-username>.github.io/<repository-name>/`.

To build it yourself: `php tools/build-preview.php _site`, then open `_site/index.html`.

## How appointment numbers work

Each weekly session has a maximum number of patients and minutes per patient. Patients get the next number in order. The estimated time is the session start plus `(number − 1) × minutes per patient`. Bookings are locked while a number is being given out, so two patients can never get the same number, and a session never goes over its maximum. Cancelled numbers are not reused, so numbers on printed lists stay stable.

Online booking closes 30 minutes before a session starts (change this in settings).

## Security

- Doctors can only see and update their own patients, and can't open the admin panel.
- The SMS API key is never shown again after it is saved.
- Admin and doctor passwords are hashed; sign-in is limited to 5 failed tries per IP address per 15 minutes; sessions time out after 2 hours idle.
- Every form is protected against cross-site request forgery.
- All database queries use prepared statements, and all output is escaped.
- Uploaded photos are checked as real images and renamed; scripts can't run from the uploads folder.
- Online bookings are limited to 3 per phone number per session and 10 per device per hour, with a hidden spam trap.
- Use HTTPS on the live site (free with cPanel AutoSSL / Let's Encrypt).

## Files

```
index.php, doctors.php, doctor.php, timetable.php   public pages
services.php, page.php, news.php, post.php          services, information pages, health tips
book.php, booking.php, check.php                    booking, confirmation, check/cancel
admin/                                              admin panel
portal/                                             doctor login area
tools/build-preview.php                             builds the github.io preview
includes/                                           config, database, shared functions and templates
assets/                                             CSS, JS, logo
data/                                               SQLite database (not in git)
uploads/doctors/, uploads/site/                     doctor and website photos (not in git)
```

## Back-ups

Download `data/family_care.sqlite` and the `uploads/` folder regularly. That is the whole database.
