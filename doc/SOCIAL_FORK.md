Hotglue Social Fork
===================

This fork adds a minimal account layer on top of Hotglue's file-based page
model.

Routes:

* `?register` creates an account and its profile page.
* `?login` starts a session.
* `?logout` ends the session.
* `?account` lets the current user set display name and upload or set an avatar.
* `?me` redirects to the current user's timeline.
* `/` redirects logged-in users to `/timeline`.
* `/timeline` shows updates and authored wall messages by the current user and
  followed users.
* `/timeline` includes a composer for posting a status update directly.
* `/feed` shows a chronological feed of profile updates.
* `?profiles` lists all profiles.
* `?follow` updates the current user's follow list.
* `?admin` lets admins manage users.
* `/u/username` shows a profile.
* `/u/username/edit` edits that profile when the logged-in user owns it.
* Legacy `?username` and `?@username` links still resolve as fallbacks.

Storage:

* Accounts are stored in `content/users.json`.
* Follow lists are stored per account in `content/users.json` under `following`.
* Optional profile avatar URLs are stored per account under `avatar_url`.
* Uploaded profile avatars are stored in `content/profile_avatars/`.
* Profile wall messages are stored in `content/social_messages.json`.
* Profile updates are stored in `content/social_updates.json`.
* Every username gets one profile page named `u_<username>.head`.
* New profiles get `social_wall` and `social_microblog` objects by default.
* Usernames are limited to lowercase letters, numbers and underscores.
* The first registered account becomes an admin.
* Usernames listed in `SOCIAL_ADMIN_USERS` are always treated as admins.

Security model:

* `AUTH_METHOD` defaults to `social`.
* JSON services still require authentication.
* Profile accounts can only mutate their own `u_<username>` page.
* Admin accounts can edit all profiles and manage user roles/statuses.
* Logged-in users can post messages on any profile wall.
* Profile owners, admins and message authors can remove wall messages.
* Profile owners and admins can post microblog updates on profile pages.
* Microblog updates are collected on the global `/feed` page.
* `/timeline` combines microblog updates with wall messages authored by people
  the current user follows, plus their own updates and wall messages.
* Feed items show a user avatar. If no `avatar_url` exists yet, rdbrr renders a
  deterministic initials avatar.
* Global operations such as changing the start page, copying pages, renaming
  pages and global grid settings are blocked for profile accounts.

This is intentionally the first foundation for a social platform. Following,
likes, email verification and password reset are not implemented yet.
