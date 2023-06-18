# E-voting SENDER

# Auth

To access the API you need to add api tokens to the .env (`API_TOKEN_LIST`) and then pass them with every request in the `Authorization` header.

# Working with Amazon

Amazon SES
<https://eu-central-1.console.aws.amazon.com/ses/home?region=eu-central-1>

Amazon SNS
<https://eu-central-1.console.aws.amazon.com/sns/v3/home?region=eu-central-1>

# Development

ngrok http sender.evote.local:80
then update the subscription:

<https://eu-central-1.console.aws.amazon.com/sns/v3/home?region=eu-central-1#/subscriptions>

you can track requests here:
<http://127.0.0.1:4040/inspect/http>

# Horizon (jobs)

<http://sender.evote.dev/horizon/dashboard>
