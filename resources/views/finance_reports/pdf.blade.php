<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kondisi Air {{ $bulanLabel }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #000;
        }

        .header {
            text-align: center;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 6px 0;
            margin-bottom: 14px;
        }

        .header .title {
            font-weight: bold;
            font-size: 14pt;
            letter-spacing: 1px;
        }

        .header .sub {
            font-weight: bold;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.report th,
        table.report td {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: top;
            overflow: hidden;
        }

        table.report th {
            background-color: #e2e2e2;
            text-align: center;
        }

        table.report col.d-no { width: 5%; }
        table.report col.d-tanggal { width: 11%; }
        table.report col.d-dropdown { width: 14%; }
        table.report col.d-lokasi { width: 13%; }
        table.report col.d-keterangan { width: 31%; }
        table.report col.d-foto { width: 26%; }

        .center {
            text-align: center;
        }

        .foto {
            width: 28mm;
            height: auto;
        }

        .muted {
            color: #999;
        }

        .summary {
            font-size: 10pt;
            margin-bottom: 10px;
        }

        .signature {
            float: right;
            margin-top: 20px;
            text-align: center;
            font-size: 10pt;
            line-height: 1.6;
            page-break-inside: avoid;
        }

        .footer {
            clear: both;
            margin-top: 12px;
            font-size: 9pt;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="title">UNIT PENGELOLA RUMAH SUSUN VI</div>
        <div class="title">LAPORAN PENCATATAN KONDISI AIR</div>
        <div class="sub">{{ $bulanLabel }}</div>
    </div>

    <div class="summary">
        <strong>Ringkasan:</strong> Normal: {{ $summary['normal'] }}
        &nbsp;&middot;&nbsp; Debit Air Kurang: {{ $summary['debit_air_kurang'] }}
        &nbsp;&middot;&nbsp; Tekanan PDAM Kurang: {{ $summary['tekanan_pdam_kurang'] }}
    </div>

    <table class="report">
        <colgroup>
            <col class="d-no">
            <col class="d-tanggal">
            <col class="d-dropdown">
            <col class="d-lokasi">
            <col class="d-keterangan">
            <col class="d-foto">
        </colgroup>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal Input</th>
                <th>Kondisi Air</th>
                <th>Lokasi</th>
                <th>Keterangan</th>
                <th>Foto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="center">{{ $row['no'] }}</td>
                    <td class="center">{{ $row['tanggal'] }}</td>
                    <td class="center">{{ $row['kondisi'] }}</td>
                    <td class="center">{{ $row['lokasi'] }}</td>
                    <td>{{ $row['keterangan'] }}</td>
                    <td class="center">
                        @if ($row['foto_src'])
                            <img src="{{ $row['foto_src'] }}" class="foto" alt="Foto">
                        @else
                            <span class="muted">Tidak ada foto</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center">Belum ada data untuk bulan ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature">
        Petugas Pencatat,<br>
        <br><br><br><br><br>
        ( . . . . . . . . . . . . )
    </div>

    <div class="footer">
         Dicetak pada {{ now()->format('d/m/Y H:i') }}.
    </div>

</body>
</html>