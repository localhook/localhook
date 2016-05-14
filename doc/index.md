Localhook
=========

Webhooks is a common thing in API world. This feature is very useful.

Therefore, it's not always easy to deal with it for developers:

- Some API required you to use HTTPS only (and not "self authenticated").
- The webhook listener has to be available on the web. Sometime **it's not possible to give a web access to your local version** of a project (entreprise proxy, etc).

Localhook is solution to help developers to work with webhooks in development context.

Get started (2 minutes to make it works perfectly!)
---------------------------------------------------

- View the [get started](get-started.md) guide.

How does this project work?
---------------------------

Here's a use case to understand the goal of this project.

### The context

Bob wants to develop a project using Google Drive API and they cool feature of Webhook, that's make him easy to informed its project when a new file arrive in a specific Drive folder.

### The problem

To develop this project, the Google Drive API has to be able to call the local bob's project endpoint as :

`http://localhost:8080/my-current-project/app_dev.php/google-drive-notifications-receiver`.

The problem is that bob works on its laptop, he likes to code everywhere and in front of him, Google have to get a fixed URL to make this feature work, as:

 `https://the-finished-project.com/google-drive-notifications-receiver`

...Dilemma !

Other dilemma :

- Bob don't care about HTTPS to develop his project, he does not even know if it will end one day ;-)

but:

- Google forces bob to use HTTPS (and not a self-signed certicate!)

### Solution

Localhook was made to fix bob's problems.

How does it works ? Bob configures its project as: "OK Google, send every webhook callbacks to https://locahook.umasoft.com/bob/my-project".

And he configure its localhook client to forward theses notification to:

http://localhost:8080/my-current-project/app_dev.php/google-drive-notifications-receiver

Now everything works fine:
- Bob doesn't have to set up a HTTPS configuration signed by an authority until its project gets in production.
- Bob can code everywhere with its laptop (no fixed IP, no dyndns to configure) and he does'nt have to set up its own IP adress on a public domain name.
- Google Drive is happy because its send its notifications to an authority signed SSL fixed domain.

License
-------
This project is under the MIT license. See the complete license at root of this project:
```
LICENCE
```

About
-----

Localhook is a [@lucascherifi](https://github.com/lucascherifi) initiative. See also the [list of contributors](https://github.com/localhook/localhook-server/graphs/contributors).
