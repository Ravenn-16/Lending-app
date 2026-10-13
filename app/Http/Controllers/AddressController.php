<?php
namespace App\Http\Controllers;
use App\Services\PhilippineAddress;
use Illuminate\Http\Request;
class AddressController extends Controller
{
    public function cities(Request $request, PhilippineAddress $addresses)
    {
        $data = $request->validate(['province_code'=>['required','string','max:20']]);
        abort_unless($addresses->find($addresses->provinces(), $data['province_code']), 404);
        return response()->json($addresses->cities($data['province_code']));
    }
    public function barangays(Request $request, PhilippineAddress $addresses)
    {
        $data = $request->validate(['province_code'=>['required','string','max:20'], 'city_code'=>['required','string','max:20']]);
        abort_unless($addresses->find($addresses->cities($data['province_code']), $data['city_code']), 404);
        return response()->json($addresses->barangays($data['city_code']));
    }
}
