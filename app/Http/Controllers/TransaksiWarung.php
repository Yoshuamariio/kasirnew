<?php

namespace App\Http\Controllers;

use App\Models\Warung;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Dompdf\Dompdf;

class PenjualanController extends Controller
{
    public function index()
    {
        return view('user_warung.transaksi');
    }

    public function data()
    {
        $penjualan = Penjualan::with('warung')->orderBy('id_penjualan', 'desc')->get();

        return datatables()
            ->of($penjualan)
            ->addIndexColumn()
            ->addColumn('total_item', function ($penjualan) {
                return format_uang($penjualan->total_item);
            })
            ->addColumn('total_harga', function ($penjualan) {
                return 'Rp. ' . format_uang($penjualan->total_harga);
            })
            ->addColumn('bayar', function ($penjualan) {
                return 'Rp. ' . format_uang($penjualan->bayar);
            })
            ->addColumn('tanggal', function ($penjualan) {
                return tanggal_indonesia($penjualan->created_at, false);
            })
            ->addColumn('kode_warung', function ($penjualan) {
                $warung = $penjualan->warung->kode_warung ?? '';
                return '<span class="label label-success">' . $warung . '</spa>';
            })
            ->editColumn('diskon', function ($penjualan) {
                return $penjualan->diskon . '%';
            })
            ->editColumn('kasir', function ($penjualan) {
                return $penjualan->user->name ?? '';
            })
            ->addColumn('aksi', function ($penjualan) {
                return '
                <div class="btn-group">
                    <button onclick="showDetail(`' . route('penjualan.show', $penjualan->id_penjualan) . '`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-eye"></i></button>
                    <button onclick="deleteData(`' . route('penjualan.destroy', $penjualan->id_penjualan) . '`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
                </div>
                ';
            })
            ->rawColumns(['aksi', 'kode_warung'])
            ->make(true);
    }

    public function create()
    {
        $penjualan = new Penjualan();
        $penjualan->id_warung = 0;
        $penjualan->total_item = 0;
        $penjualan->total_harga = 0;
        $penjualan->nama_pelanggan = '';
        $penjualan->no_tempat_duduk = 0;
        $penjualan->bayar = 0;
        $penjualan->diterima = 0;
        $penjualan->keterangan = '';
        $penjualan->id_user = auth()->id();
        $penjualan->save();

        session(['id_penjualan' => $penjualan->id_penjualan]);
        session(['id_warung' => 1]);
        return redirect()->route('transaksi.index');
    }

    public function store(Request $request)
    {


        $penjualan = Penjualan::findOrFail($request->id_penjualan);
        $penjualan->id_warung = $request->id_warung;
        $penjualan->total_item = $request->total_item;
        $penjualan->total_harga = $request->total;
        $penjualan->nama_pelanggan = $request->nama_pelanggan;
        $penjualan->no_tempat_duduk = $request->no_tempat_duduk;
        $penjualan->bayar = $request->bayar;
        $penjualan->diterima = $request->diterima;
        $penjualan->keterangan = $request->keterangan;
        $penjualan->update();

        $detail = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
        foreach ($detail as $item) {
            $item->update();

            $produk = Produk::find($item->id_produk);
            $produk->update();
        }

        return redirect()->route('transaksi.selesai');
    }

    public function show($id)
    {
        $detail = PenjualanDetail::with('produk')->where('id_penjualan', $id)->get();

        return datatables()
            ->of($detail)
            ->addIndexColumn()
            ->addColumn('kode_produk', function ($detail) {
                return '<span class="label label-success">' . $detail->produk->kode_produk . '</span>';
            })
            ->addColumn('nama_produk', function ($detail) {
                return $detail->produk->nama_produk;
            })
            ->addColumn('harga_jual', function ($detail) {
                return 'Rp. ' . format_uang($detail->harga_jual);
            })
            ->addColumn('jumlah', function ($detail) {
                return format_uang($detail->jumlah);
            })
            ->addColumn('subtotal', function ($detail) {
                return 'Rp. ' . format_uang($detail->subtotal);
            })
            ->rawColumns(['kode_produk'])
            ->make(true);
    }

    public function destroy($id)
    {
        $penjualan = Penjualan::find($id);
        $detail    = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
        foreach ($detail as $item) {
            $produk = Produk::find($item->id_produk);
            if ($produk) {
                $produk->update();
            }

            $item->delete();
        }

        $penjualan->delete();

        return response(null, 204);
    }

    public function notaKecil()
    {
        $setting = Setting::first();
        $penjualan = Penjualan::find(session('id_penjualan'));
        if (!$penjualan) {
            abort(404);
        }
        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', session('id_penjualan'))
            ->get();

        $warung = Warung::find(session('id_warung'));
        $sesi = session('id_warung');
        // $sesi = session('id_penjualan');

        return view('penjualan.nota_kecil', compact('setting', 'penjualan', 'detail', 'warung', 'sesi'));
    }

    // public function notaBesar()
    // {
    //     $setting = Setting::first();
    //     $penjualan = Penjualan::find(session('id_penjualan'));
    //     if (! $penjualan) {
    //         abort(404);
    //     }
    //     $detail = PenjualanDetail::with('produk')
    //         ->where('id_penjualan', session('id_penjualan'))
    //         ->get();

    //     $pdf = PDF::loadView('penjualan.nota_besar', compact('setting', 'penjualan', 'detail'));
    //     $pdf->setPaper(0,0,609,440, 'potrait');
    //     return $pdf->stream('Transaksi-'. date('Y-m-d-his') .'.pdf');
    // }
    public function selesai()
    {
        $setting = Setting::first();

        // Sistem Rolling
        $currentWarung = Warung::where('status_warung', 1)->first();
        // $nextMember= $currentMember->id_member;

        if ($currentWarung) {
            // Proses pesanan untuk Warung yang sedang dalam giliran

            // Perbarui status urutan untuk Warung berikutnya
            $nextWarung = Warung::where('id_warung', '>', $currentWarung->id_warung)
                ->orderBy('id_warung')
                ->first();

            // Jika tidak ada Warung berikutnya, atur giliran ke Warung pertama
            if (!$nextWarung) {
                $nextWarung = Warung::orderBy('id_warung')->first();
            }

            // // Update status urutan stand berikutnya
            $currentWarung->status_warung = 0;
            $nextWarung->status_warung = 1;

            // // Simpan perubahan status urutan untuk kedua Warung
            $currentWarung->save();
            $nextWarung->save();

            // Lakukan operasi lain yang diperlukan setelah pemrosesan pesanan
        }

        // Generate PDF
        $htmlNota = $this->notaKecil();
        $pdf = new Dompdf();
        $pdf->loadHtml($htmlNota);
        $pdf->setPaper(0, 0, 70, 500, 'potrait');
        $pdf->render();

        // Save PDF to storage
        $pdfContent = $pdf->output();
        $timestamp = date('Y-m-d_H-i-s');
        $fileName = 'nota_' . $timestamp . '.pdf';
        $fileLocation = storage_path('app/' . $fileName);
        file_put_contents($fileLocation, $pdfContent);
        $fileLoc = 'app/' . $fileName;


        // Pass data to the view
        return view('penjualan.selesai', compact('setting', 'fileLoc', 'currentWarung', 'nextWarung'));
    }
}
