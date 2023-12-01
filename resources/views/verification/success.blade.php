<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" href="/favicon.ico">
    <!-- Compiled and minified CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Karla:wght@200;400;600;700&family=Poppins:wght@100;200;400;600&display=swap"
        rel="stylesheet">
    {{-- --}}
    <title>{{ __('verification.success.title') }}</title>
    <style>
        main {
            font-family: Karla, sans-serif;
            margin: auto;
            text-align: left;
            background-color: #fff;
            padding: 30px;
        }

        h1 {
            font-family: Poppins, sans-serif;
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
        }

        .text {
            font-size: 18px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }

        .exittext {
            font-size: 14px;
            color: #718096;
            text-align: center;
            padding-top: 5%;
        }
    </style>
</head>

<body>
    <main>
        <div style="margin-left: auto; margin-right: auto; width: 100px; padding-top: 20%; padding-bottom: 5%;">
            <svg class="checkmark" width="100px" height="100px" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg">
                <!-- Circle -->
                <circle cx="25" cy="25" r="22" stroke="gray" stroke-width="2" fill="none" />

                <!-- Checkmark -->
                <path class="checkmark" fill="none" stroke="gray" stroke-width="2" d="M15,24 l8,8 l16,-16"
                    stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
        <h1>{{ __('verification.success.title') }}</h1>
        <br />
        <p class="text">{!! __('verification.success.text') !!}</p>
        <p class="exittext">{{ __('verification.success.button') }}</p>
    </main>
</body>

</html>