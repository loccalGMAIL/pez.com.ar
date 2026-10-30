        <div class="section-title">CLIENTE</div>
        <table class="client">
            <tr>
                <td>
                    <div class="field-label">Nombre / Razón social</div>
                    <div class="field-value">{{ $client->business_name }}</div>
                </td>
                <td>
                    <div class="field-label">CUIT / DNI</div>
                    <div class="field-value">{{ $client->cuit }}&nbsp;</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="field-label">Teléfono</div>
                    <div class="field-value">{{ $client->phone ?: $client->primaryContact?->phone }}&nbsp;</div>
                </td>
                <td>
                    <div class="field-label">Email</div>
                    <div class="field-value">{{ $client->email ?: $client->primaryContact?->email }}&nbsp;</div>
                </td>
            </tr>
        </table>

