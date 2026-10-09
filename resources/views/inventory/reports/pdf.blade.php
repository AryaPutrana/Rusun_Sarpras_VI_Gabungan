<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Pengambilan Material</title>

    <style>
        @page {
            margin: 20px 30px 25px 30px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #000;
        }

        .header h2 {
            margin: 0 0 4px 0;
            font-size: 15px;
            font-weight: bold;
        }

        .header h3 {
            margin: 0 0 4px 0;
            font-size: 12px;
            font-weight: normal;
        }

        .header .report-title {
            margin: 0;
            font-size: 14px;
            font-weight: bold;
        }


        /* =====================================================
           INFORMASI LAPORAN
        ===================================================== */

        .info {
            margin-bottom: 16px;
        }

        .info p {
            margin: 0 0 7px 0;
            line-height: 1.4;
        }


        /* =====================================================
           TABEL
        ===================================================== */

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 16px;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #000;
            padding: 5px 4px;
            vertical-align: middle;
        }

        .report-table th {
            background-color: #dedede;
            font-weight: bold;
            text-align: center;
            line-height: 1.2;
            font-size: 9px;
        }

        .report-table td {
            line-height: 1.3;
        }


        /* =====================================================
           LEBAR KOLOM (8 kolom baru)
        ===================================================== */

        .col-no {
            width: 3%;
        }

        .col-item-code {
            width: 9%;
        }

        .col-item-name {
            width: 24%;
        }

        .col-taken-by {
            width: 14%;
        }

        .col-date {
            width: 10%;
        }

        .col-rusun {
            width: 9%;
        }

        .col-price {
            width: 15%;
        }

        .col-subtotal {
            width: 13%;
        }


        /* =====================================================
           ITEM DISPLAY (dengan foto)
        ===================================================== */

        .item-content {
            display: table;
            width: 100%;
        }

        .item-thumb-cell {
            display: table-cell;
            width: 35px;
            vertical-align: middle;
        }

        .item-thumb {
            width: 35px;
            height: 35px;
            border: 1px solid #999;
            vertical-align: middle;
        }

        .item-text-cell {
            display: table-cell;
            padding-left: 6px;
            vertical-align: middle;
            line-height: 1.35;
        }


        /* =====================================================
           ALIGNMENT
        ===================================================== */

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-left {
            text-align: left !important;
        }

        .no-column {
            text-align: center;
            font-weight: bold;
        }


        /* =====================================================
           TOTAL
        ===================================================== */

        .total-row th,
        .total-row td {
            background-color: #dedede;
            font-weight: bold;
        }

        .total-label {
            text-align: right !important;
        }


        /* =====================================================
           SUMMARY
        ===================================================== */

        .summary {
            margin-top: 18px;
            padding: 12px 14px;
            border: 1px solid #000;
            background-color: #f7f7f7;
        }

        .summary p {
            margin: 4px 0;
            line-height: 1.35;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {
            margin-top: 45px;
            width: 100%;
            position: relative;
            min-height: 115px;
        }

        .created-date {
            position: absolute;
            left: 0;
            top: 0;
            margin: 0;
        }


        /* =====================================================
           TANDA TANGAN
        ===================================================== */

        .signature {
            position: absolute;
            right: 0;
            top: 0;
            width: 190px;
            text-align: center;
        }

        .signature-title {
            margin: 0;
        }

        .signature-space {
            height: 55px;
        }

        .signature-line {
            width: 100%;
            border-top: 1px solid #000;
            margin: 0;
        }

        .signature-name {
            margin: 7px 0 0 0;
        }


        /* =====================================================
           TIDAK ADA DATA
        ===================================================== */

        .empty-data {
            text-align: center;
            padding: 25px 10px;
            border: 1px solid #000;
        }


        /* =====================================================
           PRINT
        ===================================================== */

        @media print {
            body {
                font-size: 9px;
            }

            .report-table {
                page-break-inside: auto;
            }

            .report-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .summary {
                page-break-inside: avoid;
            }

            .footer {
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>

    <!-- =====================================================
         HEADER
    ===================================================== -->

    <div class="header">

        <h2>
            UNIT PENGELOLA RUMAH SUSUN VI
        </h2>

        <h3>
            DIVISI SARANA PRASARANA
        </h3>

        <p class="report-title">
            LAPORAN PENGAMBILAN MATERIAL
        </p>

    </div>


    <!-- =====================================================
         INFORMASI LAPORAN
    ===================================================== -->

    <div class="info">

        <p>
            <strong>Periode:</strong>
            {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d/m/Y') }}
            -
            {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d/m/Y') }}
        </p>

        <p>
            <strong>Rusun:</strong>
            {{ $rusunName }}
        </p>

    </div>


    <!-- =====================================================
         DATA PENGAMBILAN
    ===================================================== -->

    @if($groupedDetails->count() > 0)

        <table class="report-table">

            <colgroup>
                <col class="col-no">
                <col class="col-item-code">
                <col class="col-item-name">
                <col class="col-taken-by">
                <col class="col-date">
                <col class="col-rusun">
                <col class="col-price">
                <col class="col-subtotal">
            </colgroup>


            <!-- =================================================
                 HEADER TABEL
            ================================================== -->

            <thead>

                <tr>

                    <th>
                        No
                    </th>

                    <th>
                        ID<br>Barang
                    </th>

                    <th>
                        Nama Barang
                    </th>

                    <th>
                        Pengambil
                    </th>

                    <th>
                        Tanggal<br>Ambil
                    </th>

                    <th>
                        Rusun
                    </th>

                    <th>
                        Harga Satuan
                    </th>

                    <th>
                        Subtotal
                    </th>

                </tr>

            </thead>


            <!-- =================================================
                 ISI TABEL
            ================================================== -->

            <tbody>

                @php $transactionNumber = 1; @endphp

                @foreach($groupedDetails as $group)

                    @foreach($group['items'] as $index => $detail)

                        <tr>

                            <!-- NO (rowspan untuk transaksi yang sama) -->
                            @if($index == 0)
                                <td rowspan="{{ $group['item_count'] }}" class="no-column">
                                    {{ $transactionNumber++ }}
                                </td>
                            @endif


                            <!-- ID BARANG -->
                            <td class="text-center">
                                <strong>{{ $detail->item?->item_code ?? '-' }}</strong>
                            </td>


                            <!-- NAMA BARANG (dengan foto) -->
                            <td>
                                @php $thumb = $detail->item?->photoThumbnail(35); @endphp
                                <div class="item-content">
                                    @if($thumb)
                                        <div class="item-thumb-cell">
                                            <img src="{{ $thumb['data'] }}" 
                                                 width="{{ $thumb['width'] }}" 
                                                 height="{{ $thumb['height'] }}" 
                                                 alt="" 
                                                 class="item-thumb">
                                        </div>
                                    @endif
                                    <div class="item-text-cell">
                                        {{ $detail->item?->name ?? '-' }}
                                    </div>
                                </div>
                            </td>


                            <!-- PENGAMBIL (rowspan untuk transaksi yang sama) -->
                            @if($index == 0)
                                <td rowspan="{{ $group['item_count'] }}">
                                    {{ $group['withdrawal']?->taken_by ?? '-' }}
                                </td>
                            @endif


                            <!-- TANGGAL AMBIL (rowspan untuk transaksi yang sama) -->
                            @if($index == 0)
                                <td rowspan="{{ $group['item_count'] }}" class="text-center">
                                    {{ $group['withdrawal']?->taken_at ? $group['withdrawal']->taken_at->translatedFormat('d/m/Y') : '-' }}
                                    <br>
                                    {{ $group['withdrawal']?->taken_at ? $group['withdrawal']->taken_at->translatedFormat('H:i') : '-' }}
                                </td>
                            @endif


                            <!-- RUSUN (rowspan untuk transaksi yang sama) -->
                            @if($index == 0)
                                <td rowspan="{{ $group['item_count'] }}" class="text-center">
                                    {{ $group['withdrawal']?->rusun?->name ?? '-' }}
                                </td>
                            @endif


                            <!-- HARGA SATUAN -->
                            <td class="text-right">
                                Rp{{ number_format($detail->unit_price, 0, ',', '.') }}<br>
                                × {{ number_format($detail->quantity, 0, ',', '.') }} {{ $detail->item?->unit ?? '-' }}
                            </td>


                            <!-- SUBTOTAL -->
                            <td class="text-right">
                                <strong>
                                    Rp{{ number_format($detail->subtotal, 0, ',', '.') }}
                                </strong>
                            </td>

                        </tr>

                    @endforeach

                @endforeach

            </tbody>


            <!-- =================================================
                 TOTAL
            ================================================== -->

            <tfoot>

                <tr class="total-row">

                    <th colspan="6" class="total-label">
                        TOTAL ({{ $totalTransactions }} transaksi)
                    </th>

                    <th class="text-right">
                        {{ $totalQuantity }} barang
                    </th>

                    <th class="text-right">
                        Rp{{ number_format($totalValue, 0, ',', '.') }}
                    </th>

                </tr>

            </tfoot>

        </table>


        <!-- =====================================================
             RINGKASAN
        ===================================================== -->

        <div class="summary">

            <p>
                <strong>Total Transaksi:</strong>
                {{ $totalTransactions }} transaksi
            </p>

            <p>
                <strong>Total Barang Diambil:</strong>
                {{ $totalQuantity }} barang
            </p>

            <p>
                <strong>Total Nilai Pengambilan:</strong>
                Rp{{ number_format($totalValue, 0, ',', '.') }}
            </p>

        </div>


    @else

        <!-- =====================================================
             JIKA DATA KOSONG
        ===================================================== -->

        <div class="empty-data">

            Tidak ada data pengambilan pada periode yang dipilih.

        </div>

    @endif


    <!-- =====================================================
         FOOTER DAN TANDA TANGAN
    ===================================================== -->

    <div class="footer">

        <p class="created-date">

            Dibuat pada:
            {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}

        </p>


        <div class="signature">

            <p class="signature-title">
                Petugas Sarpras,
            </p>


            <div class="signature-space"></div>


            <div class="signature-line"></div>


            <p class="signature-name">

                (
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                )

            </p>

        </div>

    </div>

</body>
</html>
