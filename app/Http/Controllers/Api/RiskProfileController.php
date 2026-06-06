<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiskProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * RiskProfileController — Kuesioner & kalkulasi profil risiko investor.
 *
 * Profil risiko wajib diisi sebelum bertransaksi (regulasi OJK).
 * Kuesioner terdiri dari 10 pertanyaan dengan bobot skor berbeda.
 */
class RiskProfileController extends Controller
{
    /**
     * Daftar pertanyaan kuesioner profil risiko.
     * Setiap opsi jawaban memiliki skor yang menentukan profil risiko.
     */
    private array $questions = [
        [
            'id'       => 1,
            'question' => 'Berapa lama Anda berencana menginvestasikan uang Anda?',
            'options'  => [
                ['value' => 'a', 'label' => 'Kurang dari 1 tahun', 'score' => 1],
                ['value' => 'b', 'label' => '1 - 3 tahun',          'score' => 3],
                ['value' => 'c', 'label' => '3 - 5 tahun',          'score' => 6],
                ['value' => 'd', 'label' => 'Lebih dari 5 tahun',   'score' => 10],
            ],
        ],
        [
            'id'       => 2,
            'question' => 'Apa tujuan utama investasi Anda?',
            'options'  => [
                ['value' => 'a', 'label' => 'Menjaga nilai uang dari inflasi',              'score' => 2],
                ['value' => 'b', 'label' => 'Mendapatkan pendapatan tambahan rutin',        'score' => 4],
                ['value' => 'c', 'label' => 'Pertumbuhan modal jangka menengah',            'score' => 7],
                ['value' => 'd', 'label' => 'Memaksimalkan pertumbuhan modal jangka panjang','score' => 10],
            ],
        ],
        [
            'id'       => 3,
            'question' => 'Seberapa besar porsi penghasilan yang Anda investasikan setiap bulan?',
            'options'  => [
                ['value' => 'a', 'label' => 'Kurang dari 5%',  'score' => 2],
                ['value' => 'b', 'label' => '5% - 10%',        'score' => 4],
                ['value' => 'c', 'label' => '11% - 20%',       'score' => 7],
                ['value' => 'd', 'label' => 'Lebih dari 20%',  'score' => 10],
            ],
        ],
        [
            'id'       => 4,
            'question' => 'Jika nilai investasi Anda turun 20% dalam sebulan, apa yang akan Anda lakukan?',
            'options'  => [
                ['value' => 'a', 'label' => 'Menjual semuanya segera',                       'score' => 1],
                ['value' => 'b', 'label' => 'Menjual sebagian untuk mengurangi risiko',      'score' => 4],
                ['value' => 'c', 'label' => 'Menunggu sampai pulih kembali',                 'score' => 7],
                ['value' => 'd', 'label' => 'Membeli lebih banyak karena harga sedang murah','score' => 10],
            ],
        ],
        [
            'id'       => 5,
            'question' => 'Seberapa familiar Anda dengan produk investasi?',
            'options'  => [
                ['value' => 'a', 'label' => 'Sama sekali tidak familiar',        'score' => 1],
                ['value' => 'b', 'label' => 'Sedikit paham (deposito/tabungan)', 'score' => 3],
                ['value' => 'c', 'label' => 'Cukup paham (reksa dana/obligasi)', 'score' => 6],
                ['value' => 'd', 'label' => 'Sangat paham (saham/derivatif)',    'score' => 10],
            ],
        ],
        [
            'id'       => 6,
            'question' => 'Berapa persen penurunan nilai investasi yang masih bisa Anda terima?',
            'options'  => [
                ['value' => 'a', 'label' => 'Tidak bisa menerima penurunan sama sekali', 'score' => 1],
                ['value' => 'b', 'label' => 'Maksimal 5% penurunan',                     'score' => 3],
                ['value' => 'c', 'label' => 'Bisa menerima 10% - 20% penurunan',         'score' => 6],
                ['value' => 'd', 'label' => 'Bisa menerima lebih dari 20% penurunan',    'score' => 10],
            ],
        ],
        [
            'id'       => 7,
            'question' => 'Bagaimana kondisi keuangan Anda saat ini?',
            'options'  => [
                ['value' => 'a', 'label' => 'Pendapatan tidak stabil, banyak hutang',        'score' => 1],
                ['value' => 'b', 'label' => 'Pendapatan cukup, masih ada cicilan',           'score' => 4],
                ['value' => 'c', 'label' => 'Pendapatan stabil, sedikit kewajiban',          'score' => 7],
                ['value' => 'd', 'label' => 'Pendapatan tinggi, aset melebihi kewajiban',   'score' => 10],
            ],
        ],
        [
            'id'       => 8,
            'question' => 'Berapa lama Anda bisa hidup dari tabungan jika kehilangan penghasilan?',
            'options'  => [
                ['value' => 'a', 'label' => 'Kurang dari 3 bulan', 'score' => 1],
                ['value' => 'b', 'label' => '3 - 6 bulan',         'score' => 4],
                ['value' => 'c', 'label' => '6 - 12 bulan',        'score' => 7],
                ['value' => 'd', 'label' => 'Lebih dari 12 bulan', 'score' => 10],
            ],
        ],
        [
            'id'       => 9,
            'question' => 'Investasi mana yang lebih Anda pilih?',
            'options'  => [
                ['value' => 'a', 'label' => 'Return 3% pasti, tanpa risiko',                    'score' => 2],
                ['value' => 'b', 'label' => 'Return 7% dengan risiko turun 5%',                 'score' => 5],
                ['value' => 'c', 'label' => 'Return 15% dengan risiko turun 15%',               'score' => 7],
                ['value' => 'd', 'label' => 'Return 30%+ dengan risiko turun lebih dari 25%',   'score' => 10],
            ],
        ],
        [
            'id'       => 10,
            'question' => 'Berapa usia Anda saat ini?',
            'options'  => [
                ['value' => 'a', 'label' => '55 tahun ke atas',  'score' => 2],
                ['value' => 'b', 'label' => '45 - 54 tahun',     'score' => 4],
                ['value' => 'c', 'label' => '35 - 44 tahun',     'score' => 7],
                ['value' => 'd', 'label' => 'Di bawah 35 tahun', 'score' => 10],
            ],
        ],
    ];

    /**
     * Dapatkan daftar pertanyaan kuesioner.
     *
     * @return JsonResponse
     */
    public function getQuestions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Daftar pertanyaan profil risiko',
            'data'    => [
                'total_questions'   => count($this->questions),
                'questions'         => $this->questions,
                'scoring_guide'     => [
                    'conservative'          => 'Skor < 20: Konservatif',
                    'moderate_conservative' => 'Skor 20-39: Moderat Konservatif',
                    'moderate'              => 'Skor 40-59: Moderat',
                    'moderate_aggressive'   => 'Skor 60-79: Moderat Agresif',
                    'aggressive'            => 'Skor >= 80: Agresif',
                ],
            ],
        ]);
    }

    /**
     * Tampilkan hasil profil risiko user yang sedang login.
     *
     * @return JsonResponse
     */
    public function show(): JsonResponse
    {
        $user        = JWTAuth::user();
        $riskProfile = RiskProfile::where('user_id', $user->id)->latest()->first();

        if (!$riskProfile) {
            return response()->json([
                'success' => true,
                'message' => 'Profil risiko belum diisi.',
                'data'    => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($riskProfile->toArray(), [
                'description' => RiskProfile::getDescription($riskProfile->result),
            ]),
        ]);
    }

    /**
     * Submit jawaban kuesioner profil risiko & hitung hasilnya.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function submit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'answers'           => 'required|array|size:10',
            'answers.*.question_id' => 'required|integer|between:1,10',
            'answers.*.answer'      => 'required|string|in:a,b,c,d',
        ], [
            'answers.size'      => 'Semua 10 pertanyaan harus dijawab.',
            'answers.*.answer.in' => 'Jawaban harus berupa a, b, c, atau d.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi jawaban gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user    = JWTAuth::user();
        $answers = $request->input('answers');

        // Hitung total skor
        $totalSkor    = 0;
        $processedAnswers = [];

        foreach ($answers as $answer) {
            $questionId = $answer['question_id'];
            $jawaban    = $answer['answer'];

            // Cari pertanyaan berdasarkan ID
            $question = collect($this->questions)->firstWhere('id', $questionId);
            if (!$question) continue;

            // Cari skor dari opsi yang dipilih
            $option = collect($question['options'])->firstWhere('value', $jawaban);
            $skor   = $option ? $option['score'] : 0;

            $totalSkor += $skor;
            $processedAnswers[] = [
                'question_id' => $questionId,
                'answer'      => $jawaban,
                'score'       => $skor,
            ];
        }

        // Tentukan hasil profil risiko
        $result = RiskProfile::calculateResult($totalSkor);

        // Simpan/update profil risiko
        $riskProfile = RiskProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'answers' => $processedAnswers,
                'score'   => $totalSkor,
                'result'  => $result,
            ]
        );

        // Update user dengan hasil profil risiko
        $user->update(['risk_profile_result' => $result]);

        $description = RiskProfile::getDescription($result);

        return response()->json([
            'success' => true,
            'message' => 'Profil risiko berhasil dihitung!',
            'data'    => [
                'risk_profile_id' => $riskProfile->id,
                'score'           => $totalSkor,
                'result'          => $result,
                'label'           => $description['label'],
                'description'     => $description['description'],
                'recommended_funds' => $description['recommended'],
            ],
        ]);
    }
}
