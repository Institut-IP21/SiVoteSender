<tr>
    <td class="header">
        <a href="https://eglasovanje.si" style="display: inline-block;">
            @if (!empty($personalization) && $personalization->photo_url)
                <img src="{{ $personalization->photo_url }}" alt="Logo" width="auto" style="height: 75px">
            @else
                eGlasovanje
            @endif
        </a>
    </td>
</tr>
