<tr>
    <td class="header">
        <a href="https://eglasovanje.si" style="display:inline-block;font-family:'Poppins',Helvetica,Arial,sans-serif;font-size:26px;font-weight:700;letter-spacing:-.02em;text-decoration:none;color:#11161a;">
            @if (!empty($personalization) && $personalization->photo_url)
                <img src="{{ $personalization->photo_url }}" alt="Logo" width="auto" style="height: 75px">
            @else
                <span style="color:#34b6df;">e</span>Glasovanje
            @endif
        </a>
    </td>
</tr>
