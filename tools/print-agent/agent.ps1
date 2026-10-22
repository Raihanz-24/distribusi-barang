# ============================================================================
#  Pancor Mas - Print Agent (Windows)
# ============================================================================
#  Menerima struk ESC/POS dari aplikasi web lalu mengirimkannya MENTAH (raw)
#  ke printer thermal, tanpa lewat rendering driver/spooler. Ini cara paling
#  andal untuk printer EPPOS USB di Windows.
#
#  Cara pakai:
#    1. Pastikan printer EPPOS sudah terpasang & tampil di "Printers".
#    2. Jalankan:  powershell -ExecutionPolicy Bypass -File tools\print-agent\agent.ps1
#    3. (Opsional) set nama printer:
#         powershell -ExecutionPolicy Bypass -File tools\print-agent\agent.ps1 -PrinterName "POS-58"
#
#  Aplikasi akan otomatis mendeteksi agent di http://127.0.0.1:17777
# ============================================================================

param(
    [string]$PrinterName = "",
    [int]$Port = 17777
)

$ErrorActionPreference = "Stop"

Add-Type -AssemblyName System.Drawing

# --- Kirim byte mentah ke printer lewat Win32 spooler -----------------------
$rawPrintSource = @'
using System;
using System.IO;
using System.Runtime.InteropServices;

public class RawPrinter
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public class DOCINFOA
    {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
    }

    [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi)]
    public static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);

    [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    public static string Send(string printerName, byte[] bytes)
    {
        IntPtr hPrinter;
        if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero))
            return "OpenPrinter gagal: " + Marshal.GetLastWin32Error();

        try
        {
            DOCINFOA di = new DOCINFOA();
            di.pDocName = "Pancor Mas Receipt";
            di.pDataType = "RAW";

            if (!StartDocPrinter(hPrinter, 1, di))
                return "StartDocPrinter gagal: " + Marshal.GetLastWin32Error();

            if (!StartPagePrinter(hPrinter))
            {
                EndDocPrinter(hPrinter);
                return "StartPagePrinter gagal: " + Marshal.GetLastWin32Error();
            }

            IntPtr pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
            Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length);
            int written;
            bool ok = WritePrinter(hPrinter, pUnmanagedBytes, bytes.Length, out written);
            Marshal.FreeCoTaskMem(pUnmanagedBytes);

            EndPagePrinter(hPrinter);
            EndDocPrinter(hPrinter);

            if (!ok)
                return "WritePrinter gagal: " + Marshal.GetLastWin32Error();

            return "OK:" + written;
        }
        finally
        {
            ClosePrinter(hPrinter);
        }
    }
}
'@

if (-not ("RawPrinter" -as [type])) {
    Add-Type -TypeDefinition $rawPrintSource -Language CSharp
}

# --- Pilih printer ----------------------------------------------------------
function Get-TargetPrinter {
    param([string]$Preferred)

    $printers = Get-Printer | Select-Object -ExpandProperty Name

    if ($Preferred -and ($printers -contains $Preferred)) { return $Preferred }

    # Cari otomatis printer yang namanya mirip printer thermal.
    $guess = $printers | Where-Object { $_ -match 'POS|THERMAL|EPPOS|RECEIPT|58|80' } | Select-Object -First 1
    if ($guess) { return $guess }

    $default = (Get-CimInstance -ClassName Win32_Printer -Filter "Default = TRUE" | Select-Object -First 1).Name
    if ($default) { return $default }

    return $null
}

# --- HTTP server ------------------------------------------------------------
$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://127.0.0.1:$Port/")

try {
    $listener.Start()
} catch {
    Write-Host ""
    Write-Host "Gagal membuka port $Port. Kemungkinan agent sudah berjalan." -ForegroundColor Red
    Write-Host "Hentikan instance lain atau jalankan dengan -Port lain." -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "  Pancor Mas - Print Agent" -ForegroundColor Cyan
Write-Host "  Mendengarkan di http://127.0.0.1:$Port" -ForegroundColor Cyan

$activePrinter = Get-TargetPrinter -Preferred $PrinterName

if ($activePrinter) {
    Write-Host "  Printer: $activePrinter" -ForegroundColor Green
} else {
    Write-Host "  PERINGATAN: tidak ada printer terdeteksi." -ForegroundColor Yellow
    Write-Host "  Pasang printer EPPOS lalu jalankan ulang agent." -ForegroundColor Yellow
}

Write-Host "  Tekan Ctrl+C untuk berhenti." -ForegroundColor DarkGray
Write-Host ""

while ($listener.IsListening) {
    $context = $listener.GetContext()
    $request = $context.Request
    $response = $context.Response

    # CORS supaya halaman web (domain berbeda) boleh memanggil agent.
    $response.Headers.Add("Access-Control-Allow-Origin", "*")
    $response.Headers.Add("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
    $response.Headers.Add("Access-Control-Allow-Headers", "Content-Type, Accept")
    $response.Headers.Add("Access-Control-Allow-Private-Network", "true")

    try {
        if ($request.HttpMethod -eq "OPTIONS") {
            $response.StatusCode = 204
            $response.Close()
            continue
        }

        if ($request.HttpMethod -eq "GET" -and $request.Url.AbsolutePath -eq "/health") {
            $payload = @{
                status = "ok"
                printer = $activePrinter
                printers = @(Get-Printer | Select-Object -ExpandProperty Name)
            } | ConvertTo-Json -Compress

            $bytes = [Text.Encoding]::UTF8.GetBytes($payload)
            $response.StatusCode = 200
            $response.ContentType = "application/json"
            $response.ContentLength64 = $bytes.Length
            $response.OutputStream.Write($bytes, 0, $bytes.Length)
            $response.Close()
            continue
        }

        if ($request.HttpMethod -eq "POST" -and $request.Url.AbsolutePath -eq "/print") {
            # Baca body mentah (byte ESC/POS).
            $length = $request.ContentLength64
            $buffer = New-Object byte[] $length
            $read = 0
            while ($read -lt $length) {
                $n = $request.InputStream.Read($buffer, $read, $length - $read)
                if ($n -le 0) { break }
                $read += $n
            }

            if (-not $activePrinter) {
                $activePrinter = Get-TargetPrinter -Preferred $PrinterName
            }

            if (-not $activePrinter) {
                $msg = [Text.Encoding]::UTF8.GetBytes("Tidak ada printer terpasang di PC ini.")
                $response.StatusCode = 503
            } else {
                $result = [RawPrinter]::Send($activePrinter, $buffer)

                if ($result -like "OK:*") {
                    $sent = ($buffer.Length)
                    Write-Host ("  [cetak] $sent byte -> $activePrinter") -ForegroundColor Green
                    $msg = [Text.Encoding]::UTF8.GetBytes("Struk terkirim ke $activePrinter.")
                    $response.StatusCode = 200
                } else {
                    Write-Host ("  [gagal] $result") -ForegroundColor Red
                    $msg = [Text.Encoding]::UTF8.GetBytes("Gagal mencetak: $result")
                    $response.StatusCode = 500
                }
            }

            $response.ContentType = "text/plain; charset=utf-8"
            $response.ContentLength64 = $msg.Length
            $response.OutputStream.Write($msg, 0, $msg.Length)
            $response.Close()
            continue
        }

        $response.StatusCode = 404
        $response.Close()
    } catch {
        Write-Host ("  [error] " + $_.Exception.Message) -ForegroundColor Red
        try { $response.StatusCode = 500; $response.Close() } catch { }
    }
}
