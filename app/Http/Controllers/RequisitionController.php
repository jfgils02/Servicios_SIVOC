<?php

namespace App\Http\Controllers;

use App\Area;
use App\Requisition;
use Illuminate\Http\Request;
use App\DetailRequisition;
use App\ProvidersRequisitions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\RequisitionFile;
use App\User;
use Illuminate\Support\Facades\Storage;

class RequisitionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $idUser = auth()->id();
        $user = User::find($idUser);
        $userAdmin = $user->hasAnyRole(['admin', 'direccion', 'compras', 'lider compras']);
        if($userAdmin == true){
            $requisitions = Requisition::orderByDesc('id')->get();
        }else{
            $requisitions = Requisition::where('id_area', $user->area_id)->orderByDesc('id')->get();
        }
        //dd($userAdmin);

        $areas = Area::all();


        $test = Requisition::latest()->first();

        foreach ($requisitions as $key => $value) {

            if($files = Storage::files("public/Documents/Requisitions/Files/".$value->id."/Factura")){

                $totalFacturas = count($files);
                $requisitions[$key]->factura = true;

            }else{
                $requisitions[$key]->factura = false;

            }
        }

        return view('requisitions.requisitions', compact('requisitions','areas'));
    }

    public function newRequisition(){
        $msg = "";
        $error = false;
        $newRequisitionsCount = Requisition::latest()->first();
        $newRequisition = ($newRequisitionsCount != null) ? $newRequisitionsCount->id + 1 : 1;
        $array=["msg"=>$msg, "error"=>$error, 'newRequisition'=>$newRequisition];

        return response()->json($array);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $error = false;
        $msg = "";

        $id_user = auth()->user()->id;
        $petition = $request->all();
        //print_r($petition['item_descripcion_1']);

        $requisition = new Requisition();
        $requisition->no_requisition = $request->noRequisition;
        $requisition->id_user = $id_user;
        $requisition->id_area = $request->area_id;
        $requisition->status = "Creada";

        if ($requisition->save()) {
            for ($i=1; $i <= $request->totalItems; $i++) {
                $detailRequisition = new DetailRequisition();
                $detailRequisition->num_item = $i;
                $detailRequisition->id_classification = $petition['item_clasificacion_'.$i];
                $detailRequisition->id_requisition = $requisition->id;
                $detailRequisition->quantity = $petition['item_cantidad_'.$i];
                $detailRequisition->unit = $petition['item_unidad_'.$i];
                $detailRequisition->description = $petition['item_descripcion_'.$i];
                $detailRequisition->model = $petition['item_modelo_'.$i];
                $detailRequisition->preference = $petition['item_referencia_'.$i];
                $detailRequisition->urgency = $petition['item_urgencia_'.$i];
                $detailRequisition->status = $petition['item_status_'.$i];
                $detailRequisition->save();
            }
        } else {
            $msg = "Error al guardar la requisici??n";
            $error = true;
            $array=["msg"=>$msg, "error"=>$error];

            return response()->json($array);
        }

        $msg = "Requisici??n guardada correctamente";
        $array=["msg"=>$msg, "error"=>$error];

        return response()->json($array);


    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Requisition  $requisition
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $user = Auth::user();
        $requisition = Requisition::find($id);
        $detailRequisition = DetailRequisition::where("id_requisition", $id)->get();
        $idUser = auth()->id();
        $user = User::find($idUser);
        $userAdmin = $user->hasAnyRole(['admin', 'direccion', 'compras', 'lider compras']);
        $response = [
            'permission' => $user->area_id,
            'currentUser' => $user->id,
            'requisition'=>$requisition['id'],
            'requisition_status'=> $requisition->status,
            'no_requisition'=>$requisition['no_requisition'],
            'id_area'=>$requisition['id_area'],
            'id_user'=>$requisition['id_user'],
            'detailRequisition'=>$detailRequisition,
            'edit'=>$userAdmin
        ];
        //$array=["requisition"=>$requisition, "detailRequisition"=>$detailRequisition];
        return response()->json($response);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Requisition  $requisition
     * @return \Illuminate\Http\Response
     */
    public function edit(Requisition $request, $id)
    {

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Requisition  $requisition
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Requisition $requisition)
    {

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Requisition  $requisition
     * @return \Illuminate\Http\Response
     */
    public function destroy(Requisition $requisition)
    {
        //
    }

    public function uploadFile(Request $request, $idRequisition){
        $error=false;
        $msg="";


        $tipo = "";
        if($request->tipo != "normal"){
            $tipo = "Factura/";
        }
        $pathFile = 'public/Documents/Requisitions/Files/'.$idRequisition.'/'.$tipo;

        for ($i=0; $i <$request->tamanoFiles ; $i++) {
            $nombre="file".$i;
            $archivo = $request->file($nombre);



            $requisitionFile=RequisitionFile::create([
                'requisition_id' => $request->id,
                'name' => $archivo->getClientOriginalName(),
                'ruta' => 'storage/Documents/Requisitions/Files/'.$idRequisition.'/'.$tipo,

            ]);
            $path = $archivo->storeAs(
                $pathFile, $archivo->getClientOriginalName()
            );
        }

        if ($requisitionFile->save()) {
            $msg="Registro guardado con exito";
        }else{
            $error=true;
            $msg="Error al guardar archvio";
        }



        $array=["msg"=>$msg, "error"=>$error];

        return response()->json($array);
    }

    public function providers($detail){
        $providers = ProvidersRequisitions::where("id_detail_requisition", $detail)->get();
        $response = [
            'data'=>$providers,
        ];
        return response()->json($response);
    }

    public function saveProvider(Request $request){
        $providerRequisitionUno = new ProvidersRequisitions();
        $providerRequisitionUno->id_detail_requisition = $request->id_detail_requisition;
        $providerRequisitionUno->num_item = $request->num_item;
        $providerRequisitionUno->name = $request->name;
        $providerRequisitionUno->unit_price = $request->unit_price;
        if($providerRequisitionUno->save()){
            $msg = "Proveedor Guardado Correctamente";
            $error = false;
            $array=["msg"=>$msg, "error"=>$error, "provider"=>$providerRequisitionUno];
        }else{
            $msg = "Error al guardar la requisici??n";
            $error = true;
            $array=["msg"=>$msg, "error"=>$error];
        }

        return response()->json($array);

    }

    public function deleteProvider($id){
        $providerRequisitionUno = ProvidersRequisitions::find($id);
        if($providerRequisitionUno->delete()){
            $msg = "Proveedor Eliminado Correctamente";
            $error = false;
            $array=["msg"=>$msg, "error"=>$error];
        }else{
            $msg = "Error al eliminar la requisici??n";
            $error = true;
            $array=["msg"=>$msg, "error"=>$error];
        }

        return response()->json($array);

    }

    public function customUpdate(Request $request, $id){
        $error = false;
        $msg = "";
        $deleteItems = [];
        $petition = $request->all();
        $requisition = Requisition::find($id);
        $reqUpdate = [
            'no_requisition' => $request->noRequisition,
            'id_area' => $request->area_id,
        ];

        if ($requisition->update($reqUpdate)) {
            for ($i=1; $i <= $request->totalItems; $i++) {
                if($petition['item_id_'.$i] != "null"){
                    $detailRequisition = DetailRequisition::find($petition['item_id_'.$i]);
                    array_push($deleteItems, $petition['item_id_'.$i]);
                    $detUpdate = [
                        'num_item' => $i,
                        'id_classification' => $petition['item_clasificacion_'.$i],
                        'id_requisition' => $id,
                        'quantity' => $petition['item_cantidad_'.$i],
                        'unit' => $petition['item_unidad_'.$i],
                        'description' => $petition['item_descripcion_'.$i],
                        'model' => $petition['item_modelo_'.$i],
                        'preference' => $petition['item_referencia_'.$i],
                        'urgency' => $petition['item_urgencia_'.$i],
                        'status' => $petition['item_status_'.$i],
                    ];
                    /*if($i==4){
                        dd($detUpdate);
                    }*/

                    if(!$detailRequisition->update($detUpdate)){
                        $msg = "Error al actualizar la requisici??n";
                        $error = true;
                        $array=["msg"=>$msg, "error"=>$error];

                        return response()->json($array);
                    }
                }else{
                    $detailRequisition = new DetailRequisition();
                    $detailRequisition->num_item = $i;
                    $detailRequisition->id_classification = $petition['item_clasificacion_'.$i];
                    $detailRequisition->id_requisition = $requisition->id;
                    $detailRequisition->quantity = $petition['item_cantidad_'.$i];
                    $detailRequisition->unit = $petition['item_unidad_'.$i];
                    $detailRequisition->description = $petition['item_descripcion_'.$i];
                    $detailRequisition->model = $petition['item_modelo_'.$i];
                    $detailRequisition->preference = $petition['item_referencia_'.$i];
                    $detailRequisition->urgency = $petition['item_urgencia_'.$i];
                    $detailRequisition->status = $petition['item_status_'.$i];

                    if(!$detailRequisition->save()){
                        $msg = "Error al actualizar la requisici??n";
                        $error = true;
                        $array=["msg"=>$msg, "error"=>$error];

                        return response()->json($array);
                    }
                    array_push($deleteItems, $detailRequisition->id);
                }
            }
            $objItems = DetailRequisition::where('id_requisition', $requisition->id)->whereNotIn('id', $deleteItems)->get();
            DetailRequisition::destroy($objItems->toArray());


        } else {
            $msg = "Error al actualizar la requisici??n";
            $error = true;
            $array=["msg"=>$msg, "error"=>$error];

            return response()->json($array);
        }

        $msg = "Requisici??n actualizada correctamente";
        $array=["msg"=>$msg, "error"=>$error];

        return response()->json($array);
    }

    public function files($idRequisition)
    {
        $requisitionFiles = Requisition::find($idRequisition)->requisitionFiles;
        $totalFacturas=0;
        if($files = Storage::files("public/Documents/Requisitions/Files/".$idRequisition."/Factura")){

            $totalFacturas = count($files);
        }
        $idUser = auth()->id();
        $user = User::find($idUser);
        $userAdmin = $user->hasRole('admin');

        $array=["requisitionFiles"=>$requisitionFiles, "userAdmin"=>$userAdmin, "totalFacturas"=>$totalFacturas];

        return response()->json($array);
    }

    public function updateStatusRequisition(Request $request, $id){
        $msg = "";
        $error = false;
        $requisition = Requisition::find($id);
        $requisition->status = $request->status;

        if(!$requisition->update()){
            $error = true;
            $msg = "Error al actualizar el status";
        }{
            $msg = "Se actualizo el status de la requisici??n";
        }

        $array = ["msg"=>$msg, "error"=>$error];
        return response()->json($array);
    }

    public function deleteFile($id)
    {
        $msg="";
        $error=false;

        $file = RequisitionFile::find($id);
        $path = 'public/Documents/Requisitions/Files/'.$file->requisition_id.'/Factura/';

        if(!Storage::delete($path.$file->name)){
            $msg = "No se puede Eliminar el archivo";
            $test = Storage::files($path);

        }

        $file->delete();
        $array=["msg"=>$msg, "error"=>$error];

        return response()->json($array);
    }
}
