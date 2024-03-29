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
use App\Project;
use App\RequisitionHistory;
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
            //$requisitions = Requisition::orderByDesc('id')->get();
            $requisitions = DB::table('requisitions')
            ->join('users', 'users.id', '=', 'requisitions.id_user')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('projects', 'requisitions.id_project', '=', 'projects.id')
            ->select('requisitions.*', 'users.name', 'users.last_name', 'role_user.role_id as role', 'projects.name_project as name_project', 'projects.adicional')
            ->orderByDesc('id')->get();
        }else{
            //$requisitions = Requisition::where('id_area', $user->area_id)->orderByDesc('id')->get();
            $requisitions = DB::table('requisitions')
            ->join('users', 'users.id', '=', 'requisitions.id_user')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('projects', 'requisitions.id_project', '=', 'projects.id')
            ->select('requisitions.*', 'users.name', 'users.last_name', 'role_user.role_id as role', 'projects.name_project as name_project', 'projects.adicional')
            ->where('requisitions.id_area', $user->area_id)
            ->orderByDesc('id')->get();
        }
        //dd($requisitions);

        $areas = Area::all();
        $areaUser = Area::find($user->area_id);

        $test = Requisition::latest()->first();

        foreach ($requisitions as $key => $value) {

            if($files = Storage::files("public/Documents/Requisitions/Files/".$value->id."/Factura")){

                $totalFacturas = count($files);
                $requisitions[$key]->factura = true;

            }else{
                $requisitions[$key]->factura = false;

            }
        }

        //PROYECTOS
        $proyectos = Project::orderBy('name_project', 'DESC')->get();

        return view('requisitions.requisitions', compact('requisitions','areas', 'areaUser', 'proyectos'));
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
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $error = false;
        $msg = "";
        $trueDireccion="false";

        $id_user = auth()->user()->id;
        $petition = $request->all();
        //dd($petition);
        //print_r($petition['item_descripcion_1']);

        $valRequi = Requisition::where('no_requisition', $request->noRequisition)->first();
        $newRequi = "";

        $requisition = new Requisition();
        //se valida si ya existe la requisicion crea una nueva
        if($valRequi){
            $arrayRequi = explode('-',$valRequi->no_requisition);
            $newRequi = 'R-'. ($arrayRequi[1] + 1);
            $requisition->no_requisition = $newRequi;
        }else{
            $requisition->no_requisition = $request->noRequisition;
        }

        $requisition->id_user = $id_user;
        $requisition->id_area = $request->area_id;
        $requisition->id_project = $request->project_id;
        //si el usuario tiene el rol de direccion la requisicon se crea con estatus Procesada
        foreach (auth()->user()->roles as $roles) {
            if($roles->name == 'direccion' || $roles->name == 'admin'){
                $requisition->status = "Procesada";
                $trueDireccion="true";
                break;
            }else{
                $requisition->status = "Creada";
            }
        }
        DB::beginTransaction();

        try {
            $requisition->save();
            for ($i=1; $i <= $request->totalItems; $i++) {
                $detailRequisition = new DetailRequisition();
                $detailRequisition->num_item = $i;
                $detailRequisition->id_classification = $petition['item_clasificacion_'.$i];
                $detailRequisition->id_requisition = $requisition->id;
                $detailRequisition->quantity = $petition['item_cantidad_'.$i];
                $detailRequisition->unit = $petition['item_unidad_'.$i];
                $detailRequisition->description = $petition['item_descripcion_'.$i];
                $detailRequisition->model = $petition['item_modelo_'.$i] == null? '' : $petition['item_modelo_'.$i];
                $detailRequisition->preference = $petition['item_referencia_'.$i] == null? '' : $petition['item_referencia_'.$i];
                $detailRequisition->urgency = $petition['item_urgencia_'.$i];
                //si el rol del usuario es direccion, el stratus de la partida sera procesada
                if($trueDireccion){
                    $detailRequisition->status = "Procesada";
                }else{
                    $detailRequisition->status = $petition['item_status_'.$i];
                }

                $detailRequisition->save();
            }
            //se crea registro para el historial
            $requisitionHistory = new RequisitionHistory();
            $requisitionHistory->requisition_id = $requisition->id;
            $requisitionHistory->status = $requisition->status;
            $requisitionHistory->user_id = $id_user;
            $requisitionHistory->save();
            DB::commit();
        }
        catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }



        $msg = "Requisición guardada correctamente";
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
        $userAdmin = false;
        switch ($requisition->id_area) {
            case 1:
                if ($requisition->status=='Creada' && $user->hasAnyRole(['admin', 'direccion', 'lider calidad'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Procesada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Cotizada' && $user->hasAnyRole(['admin', 'direccion'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Aprobada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Entregada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    //$userAdmin=true;
                }elseif ($requisition->status=='Devolucion' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }
                break;
            case 2:
                if ($requisition->status=='Creada' && $user->hasAnyRole(['admin', 'direccion', 'lider tesoreria'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Procesada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Cotizada' && $user->hasAnyRole(['admin', 'direccion'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Aprobada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Entregada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    //$userAdmin=true;
                }elseif ($requisition->status=='Devolucion' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }
                break;
            case 3:
                if ($requisition->status=='Creada' && $user->hasAnyRole(['admin', 'direccion', 'lider compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Procesada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Cotizada' && $user->hasAnyRole(['admin', 'direccion'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Aprobada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Entregada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    //$userAdmin=true;
                }elseif ($requisition->status=='Devolucion' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }
                break;

            case 5:
                if ($requisition->status=='Creada' && $user->hasAnyRole(['admin', 'direccion', 'lider recursos humanos'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Procesada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Cotizada' && $user->hasAnyRole(['admin', 'direccion'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Aprobada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Entregada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    //$userAdmin=true;
                }elseif ($requisition->status=='Devolucion' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }
                break;
            case 6:
                if ($requisition->status=='Creada' && $user->hasAnyRole(['admin', 'direccion', 'lider ventas'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Procesada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Cotizada' && $user->hasAnyRole(['admin', 'direccion'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Aprobada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Entregada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    //$userAdmin=true;
                }elseif ($requisition->status=='Devolucion' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }
                break;
            case 7:
                if ($requisition->status=='Creada' && $user->hasAnyRole(['admin', 'direccion', 'lider servicio'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Procesada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Cotizada' && $user->hasAnyRole(['admin', 'direccion'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Aprobada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }elseif ($requisition->status=='Entregada' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    //$userAdmin=true;
                }elseif ($requisition->status=='Devolucion' && $user->hasAnyRole(['admin', 'lider compras', 'compras'])) {
                    $userAdmin=true;
                }
                break;


            default:
                # code...
                break;
        }
        $response = [
            'permission' => $user->area_id,
            'currentUser' => $user->id,
            'requisition'=>$requisition['id'],
            'requisition_status'=> $requisition->status,
            'no_requisition'=>$requisition['no_requisition'],
            'id_area'=>$requisition['id_area'],
            'id_user'=>$requisition['id_user'],
            'id_project'=>$requisition['id_project'],
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
                'comment' =>'',

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
            $msg = "Error al guardar la requisición";
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
            $msg = "Error al eliminar la requisición";
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
        $idUser = auth()->user()->id;

        $reqUpdate = [
            'no_requisition' => $request->noRequisition,
            'id_area' => $request->area_id,
            'id_project' => $request->project_id,
        ];

        $estatusProcesada = 0;
        $estatusCotizada = 0;
        $estatusEntregada = 0;
        $estatusDevolucion = 0;
        $estatusCancelada = 0;

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
                   switch ($petition['item_status_'.$i]) {
                    case 'Procesada':
                        $estatusProcesada++;
                        break;
                    case 'Cotizada':
                        $estatusCotizada++;
                        break;
                    case 'Entregada':
                        $estatusEntregada++;
                        break;
                    case 'Devolucion':
                        $estatusDevolucion++;
                        break;
                    case 'Cancelada':
                        $estatusCancelada++;
                        break;
                   }

                    if(!$detailRequisition->update($detUpdate)){
                        $msg = "Error al actualizar la requisición";
                        $error = true;
                        $array=["msg"=>$msg, "error"=>$error];

                        return response()->json($array);
                    }
                }else{
                    switch ($petition['item_status_'.$i]) {
                        case 'Procesada':
                            $estatusProcesada++;
                            break;
                        case 'Cotizada':
                            $estatusCotizada++;
                            break;
                        case 'Entregada':
                            $estatusEntregada++;
                            break;
                        case 'Devolucion':
                            $estatusDevolucion++;
                            break;
                        case 'Cancelada':
                            $estatusCancelada++;
                            break;
                    }
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
                        $msg = "Error al actualizar la requisición";
                        $error = true;
                        $array=["msg"=>$msg, "error"=>$error];

                        return response()->json($array);
                    }
                    array_push($deleteItems, $detailRequisition->id);
                }
            }

            $estatusActual = ['status' =>"Creada"];
            if($estatusProcesada > 0 || ($estatusCotizada < 0 || $estatusEntregada < 0 || $estatusDevolucion < 0)){
                $estatusActual = ['status' =>"Procesada"];

            }elseif($estatusProcesada > 0 && ($estatusCotizada > 0 || $estatusEntregada > 0 || $estatusDevolucion > 0)){
                $estatusActual = ['status' =>"Procesada"];
            }elseif ($estatusProcesada <= 0 && $estatusCotizada > 0 && $estatusEntregada <= 0 && $estatusDevolucion <= 0) {
                $estatusActual = ['status' =>"Cotizada"];
            }elseif ($estatusProcesada <= 0 && $estatusCotizada <= 0 && $estatusEntregada > 0 && $estatusDevolucion <= 0) {
                $estatusActual = ['status' =>"Entregada"];
            }elseif ($estatusProcesada <= 0 && $estatusCotizada <= 0 && $estatusEntregada <= 0 && $estatusDevolucion > 0) {
                $estatusActual = ['status' =>"Devolucion"];
            }elseif ($estatusCancelada == $request->totalItems) {
                $estatusActual = ['status' =>"Cancelada"];
            }
            //dd($estatusActual);
            if($requisition->update($estatusActual)){
                $objItems = DetailRequisition::where('id_requisition', $requisition->id)->whereNotIn('id', $deleteItems)->get();

                //se crea registro para el historial
                $requisitionHistory = new RequisitionHistory();
                $requisitionHistory->requisition_id = $requisition->id;
                $requisitionHistory->status = $requisition->status;
                $requisitionHistory->user_id = $idUser;
                $requisitionHistory->save();

                DetailRequisition::destroy($objItems->toArray());
            }else{
                $msg = "Error al actualizar la requisición";
                $error = true;
                $array=["msg"=>$msg, "error"=>$error];

                return response()->json($array);
            }

        } else {
            $msg = "Error al actualizar la requisición";
            $error = true;
            $array=["msg"=>$msg, "error"=>$error];

            return response()->json($array);
        }

        $msg = "Requisición actualizada correctamente";
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
        $userCompras = $user->hasAnyRole(['compras', 'lider compras']);

        $array=["requisitionFiles"=>$requisitionFiles, "userAdmin"=>$userAdmin, "totalFacturas"=>$totalFacturas, "userCompras"=>$userCompras];

        return response()->json($array);
    }

    public function updateStatusRequisition(Request $request, $id){
        $msg = "";
        $error = false;
        $requisition = Requisition::find($id);
        $detailRequisition = [];
        $idUser = auth()->user()->id;

        switch ($request->status) {
            case 'Creada':
                $detailRequisition = DetailRequisition::where('status', '!=', 'Cancelada')->get();
                break;
            case 'Procesada':
                $detailRequisition = DetailRequisition::where('status', '!=', 'Cancelada')->get();
                break;
            case 'Cotizada':
                $detailRequisition = DetailRequisition::where('status', '!=', 'Cancelada')->get();
                break;
            case 'Aprobada':
                $detailRequisition = DetailRequisition::where('status', '!=', 'Cancelada')->get();
                break;
            case 'Entregada':
                $detailRequisition = DetailRequisition::where('status', '!=', 'Cancelada')->get();
                break;

            default:
                # code...
                break;
        }
        $requisition->status = $request->status;

        if(!$requisition->update()){
            $error = true;
            $msg = "Error al actualizar el status";
        }{
            foreach ($detailRequisition as $detail) {
                $detail->status = $request->status;
                $detail->update();
            }
            //se crea registro para el historial
            $requisitionHistory = new RequisitionHistory();
            $requisitionHistory->requisition_id = $requisition->id;
            $requisitionHistory->status = $requisition->status;
            $requisitionHistory->user_id = $idUser;
            $requisitionHistory->save();
            $msg = "Se actualizo el status de la requisición";
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

    public function saveComment(Request $request, $id){
        $file = RequisitionFile::find($id);
        $file->comment = $request->comment;
        $error = false;
        $msg = "";
        if(!$file->save()){
            $msg = " No se pudo Guardar el comentario";
            $error = true;
        }
        $array = ["mgs"=>$msg, "error"=>$error];
        return response()->json($array);
    }

    public function history($id)
    {
        $requisitionHistory = DB::table('requisition_histories')
            ->join('users', 'users.id', '=', 'requisition_histories.user_id')
            ->join('requisitions', 'requisitions.id', '=', 'requisition_histories.requisition_id')
            ->select('requisition_histories.*', 'users.name', 'users.last_name')
            ->where('requisition_histories.requisition_id', $id)
            ->get();

        $msg = "";
        $error=false;
        $array = ["mgs"=>$msg, "error"=>$error, "requisitionHistory"=>$requisitionHistory];
        return response()->json($array);
    }

}
