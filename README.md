# E-voting SENDER

# SiVote Engine

SiVote is a internet voting that supports **secret ballots** and is intended **for private organizations**. System is comprised of at least two modules - you'll also need the [SiVote Engine](https://github.com/Institut-IP21/SiVoteEngine).

SiVote Sender contains functionality connected to managing lists of voters and sending emails (including ballot invitations). 

## Hosted version

You can use the hosted version at [eGlasovanje.si](https://eglasovanje.si/) that's free for smaller organizations. 

## Installation

You can use the included Docker compose image or deploy directly (see image for requirements).

```bash
    cp .env.example .env
    docker-compose up -d
    docker-compose exec evote_app bash
    composer install
    php artisan migrate
    yarn install
    yarn dev
```

### Working with Amazon

You'll need to setup [Amazon SES](https://eu-central-1.console.aws.amazon.com/ses/home?region=eu-central-1) (for email sending) and [Amazon SNS](https://eu-central-1.console.aws.amazon.com/sns/v3/home?region=eu-central-1) (to track email delivery).

### Development

To enable email sending to work properly during local development we suggest you use ngrok

```bash
    ngrok http sender.evote.local:80
```
then update the [subscription](https://eu-central-1.console.aws.amazon.com/sns/v3/home?region=eu-central-1#/subscriptions):
    
## Learn more

We've published a number of articles explaning all the aspects of the model and system on the [eGlasovanje.si website](https://eglasovanje.si/vsi-clanki)

## Feedback & Support

If you have any feedback or need support, please reach out to us at info@ip21.si


