#!/usr/bin/env node
/** Fifty independent HTTP sessions against a disposable, loopback-only Laravel app. */
import http from 'node:http';
import net from 'node:net';
import {spawn, spawnSync} from 'node:child_process';
import {createHash, randomBytes} from 'node:crypto';
import {mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync, openSync, closeSync, existsSync} from 'node:fs';
import {tmpdir, cpus, platform} from 'node:os';
import {resolve, dirname, join} from 'node:path';
import {fileURLToPath} from 'node:url';
import {setTimeout as pause} from 'node:timers/promises';

const here = dirname(fileURLToPath(import.meta.url));
const project = resolve(here, '../..');
const options = {duration:60, workers:8, history:500, rounds:0, memory:null, p95:2000, p99:5000, output:resolve(here, 'results', new Date().toISOString().replace(/[:.]/g, '-'))};
for (const argument of process.argv.slice(2)) {
    const match = /^--(duration|workers|history|rounds|memory|p95|p99|output)=(.+)$/.exec(argument);
    if (!match) throw new Error('Use --duration=60 --workers=8 --history=500 --rounds=0 --p95=2000 --p99=5000 --output=/path');
    options[match[1]] = match[1] === 'output' ? resolve(match[2]) : (match[1] === 'memory' ? match[2] : Number(match[2]));
}
for (const key of ['duration','workers','history','rounds','p95','p99']) {
    if (!Number.isFinite(options[key]) || !Number.isInteger(options[key]) || options[key] < 0) throw new Error('Invalid '+key);
}
if (options.workers < 2 || options.workers > 64 || options.duration > 3600 || options.history > 10000 || (!options.duration && !options.rounds)) throw new Error('Use 2–64 workers, at most 3600 seconds and 10000 history records; supply duration or rounds.');
if(options.memory && !/^[1-9][0-9]*[KMG]$/.test(options.memory))throw new Error('Use --memory=128M (positive K/M/G size).');
if (platform() === 'win32') throw new Error('Use WSL2/Linux for PHP multi-worker load tests.');
const php = process.env.PHP_BINARY || 'php';
const run = mkdtempSync(join(tmpdir(), 'ipg-load-'));
const token = randomBytes(32).toString('hex');
const password = randomBytes(24).toString('hex');
writeFileSync(join(run, 'run.json'), JSON.stringify({project, token_hash:createHash('sha256').update(token).digest('hex')}), {mode:0o600});
for (const directory of ['bootstrap/cache','storage/framework/views','storage/framework/sessions','storage/framework/cache/data','storage/logs']) mkdirSync(join(run,directory), {recursive:true});
const environment = {...process.env, IPG_LOAD_RUN_DIR:run, IPG_LOAD_TOKEN:token, APP_KEY:'base64:'+randomBytes(32).toString('base64')};
const failures = [];
const samples = [];
const workerPids = new Set();
const protectedPaths = ['.env', 'database/database.sqlite', 'composer.json', 'composer.lock'].map(name => join(project,name));
const fingerprint = path => existsSync(path) ? createHash('sha256').update(readFileSync(path)).digest('hex') : null;
const protectedBefore = protectedPaths.map(fingerprint);
let inFlight=0, peakInFlight=0, activeUsers=0, peakActiveUsers=0;
let server, logHandle, fixtureData, clients=[], completedRounds=0, report;
let workloadStart=0, workloadEnd=0, mixedSeconds=0, observedMemoryLimit=null, interrupted=false;
for(const signal of ['SIGINT','SIGTERM'])process.once(signal,()=>{
    interrupted=true;
    for(const client of clients)client.agent.destroy();
    void stopServer();
});
const recordFailure = (message, extra={}) => failures.push({message,...extra});
function fixture(mode, input) {
    const result = spawnSync(php, [join(here,'fixture.php'),mode], {cwd:project,env:environment,input:JSON.stringify(input),encoding:'utf8',maxBuffer:16*1024*1024,timeout:120000});
    if (result.error || result.status !== 0) throw new Error('Fixture '+mode+' failed: '+(result.error?.message || result.stderr || result.stdout).slice(-1500));
    try { return JSON.parse(result.stdout); } catch { throw new Error('Fixture did not return JSON: '+result.stdout.slice(-1000)); }
}
function makeClient(user={index:-1,group:'guest'}) {
    return {...user,cookies:new Map(),csrf:null,agent:new http.Agent({keepAlive:true,maxSockets:1}),localAddress:platform()==='linux' ? '127.0.0.'+(user.index+2) : '127.0.0.1'};
}
async function request(client, method, path, data, {phase='mixed',operation=path,expected=[200],csrf=true}={}) {
    const body = data ? new URLSearchParams(Object.fromEntries(Object.entries(data).map(([k,v])=>[k,String(v)]))).toString() : null;
    const started=performance.now();
    inFlight++; if (phase !== 'warmup' && phase !== 'health') peakInFlight=Math.max(peakInFlight,inFlight);
    let status=0, bytes=0, result, unexpected=false;
    try {
        result = await new Promise((resolveRequest,reject)=>{
            const headers={Accept:'application/json',Cookie:[...client.cookies].map(([k,v])=>k+'='+v).join('; '),Referer:environment.APP_URL+'/admin/bookings/manage?tab=pending'};
            if (csrf && client.csrf) headers['X-CSRF-TOKEN']=client.csrf;
            if (body !== null) {headers['Content-Type']='application/x-www-form-urlencoded';headers['Content-Length']=Buffer.byteLength(body);}
            const req=http.request(new URL(path,environment.APP_URL),{method,headers,agent:client.agent,localAddress:client.localAddress},res=>{
                const chunks=[];
                for (const value of res.headers['set-cookie'] || []) {
                    const cookie=value.split(';',1)[0], split=cookie.indexOf('=');
                    client.cookies.set(cookie.slice(0,split),cookie.slice(split+1));
                }
                res.on('data',chunk=>{bytes+=chunk.length;chunks.push(chunk);if(bytes>16*1024*1024)req.destroy(new Error('Response exceeds 16 MiB'));});
                res.on('error',reject);
                res.on('end',()=>resolveRequest({status:res.statusCode,headers:res.headers,body:Buffer.concat(chunks).toString('utf8')}));
            });
            req.setTimeout(30000,()=>req.destroy(new Error('Request exceeded 30s')));
            req.on('error',reject);if(body !== null)req.write(body);req.end();
        });
        status=result.status;
        if(result.headers['x-ipg-load-memory-limit'])observedMemoryLimit=result.headers['x-ipg-load-memory-limit'];
        if (result.headers['x-ipg-load-worker']) workerPids.add(result.headers['x-ipg-load-worker']);
        if (!expected.includes(status)) {
            unexpected=true;
            let detail=''; try {const error=JSON.parse(result.body);detail=JSON.stringify({message:error.message,errors:error.errors}).slice(0,400);} catch {}
            recordFailure('Unexpected HTTP status',{phase,operation,user:client.index+1,status,expected,detail});
        }
        if (phase !== 'warmup' && status===302 && /\/login(?:\?|$)/.test(result.headers.location || '')) {unexpected=true;recordFailure('Authenticated user redirected to login',{phase,operation,user:client.index+1});}
        return result;
    } catch(error) {
        unexpected=true;
        recordFailure(error.message,{phase,operation,user:client.index+1});
        return {status:0,headers:{},body:''};
    } finally {
        inFlight--;samples.push({phase,operation,group:client.group,user:client.index+1,status,expected:expected.join('|'),unexpected,ms:performance.now()-started,bytes});
    }
}
function csrfFrom(body) {
    return /<meta name="csrf-token" content="([^"]+)"/.exec(body)?.[1]
        || /name="_token" value="([^"]+)"/.exec(body)?.[1];
}
async function authenticate(client) {
    const login=await request(client,'GET','/login',null,{phase:'warmup',operation:'login_form'});
    client.csrf=csrfFrom(login.body);
    if (!client.csrf) throw new Error('Missing CSRF token on login form.');
    for(let attempt=0;attempt<4;attempt++) {
        const response=await request(client,'POST','/login',{email:client.email,password},{phase:'warmup',operation:'login',expected:[302,429]});
        if(response.status===429){await pause(Math.min(61000,1000*(Number(response.headers['retry-after']) || 60))+100);continue;}
        if(response.status!==302 || !/\/dashboard$/.test(response.headers.location || '')) throw new Error('Login failed for synthetic user '+(client.index+1));
        const dashboard=await request(client,'GET','/dashboard',null,{phase:'warmup',operation:'authenticated_dashboard'});
        client.csrf=csrfFrom(dashboard.body);
        if(dashboard.status!==200 || !client.csrf)throw new Error('Session did not authenticate.');
        return;
    }
    throw new Error('Warmup login remained throttled.');
}
async function barrier(action) {
    await Promise.all(clients.map(async client=>{
        activeUsers++;peakActiveUsers=Math.max(peakActiveUsers,activeUsers);
        try {await action(client);} catch(error) {recordFailure(error.message,{user:client.index+1});}
        finally {activeUsers--;}
    }));
}
function bookingData(round,index,purpose) {
    const day=new Date(fixtureData.base_date+'T00:00:00Z');day.setUTCDate(day.getUTCDate()+Math.floor(round/100));
    const date=day.toISOString().slice(0,10), minutes=480+(round%100)*5;
    const time=n=>String(Math.floor(n/60)).padStart(2,'0')+':'+String(n%60).padStart(2,'0');
    return {facility_id:fixtureData.facilities[index],start_date:date,end_date:date,start_time:time(minutes),end_time:time(minutes+1),purpose};
}
async function browse(client,round,step) {
    const paths=['/dashboard','/calendar?facility='+fixtureData.facilities[(client.index+round)%25]+'&week='+fixtureData.base_date,'/staff/my-bookings?status=approved','/staff/bookings/create'];
    const path=paths[(client.index+round+step)%paths.length];
    return request(client,'GET',path,null,{operation:path.split('?')[0]});
}
async function mixedRound(round) {
    await barrier(async client=>{
        if(client.group==='booking')await request(client,'POST','/staff/bookings',bookingData(round,client.index,'mixed:'+round+':'+client.index),{operation:'create_booking',expected:[302]});
        else if(client.group==='admin')await request(client,'GET','/admin/bookings/manage?tab=pending',null,{operation:'pending_queue'});
        else await browse(client,round,0);
    });
    await barrier(async client=>{
        if(client.group==='booking'){
            const response=await request(client,'GET','/staff/my-bookings?q='+encodeURIComponent('mixed:'+round+':'+client.index),null,{operation:'find_own_booking'});
            client.bookingId=Number(/href="[^"\s]*\/bookings\/(\d+)"/.exec(response.body)?.[1]);
            if(!client.bookingId)throw new Error('Created booking missing from its owner’s filtered list.');
        }else if(client.group==='admin')await request(client,'GET','/admin/bookings?status=pending',null,{operation:'filter_pending'});
        else await browse(client,round,1);
    });
    await barrier(async client=>{
        if(client.group==='admin'){
            const offset=(client.index-45)*5;
            for(const kind of [0,1,3]){
                const booking=clients[offset+kind].bookingId;if(!booking)continue;
                const decision=kind===1?'reject':'approve';
                await request(client,'PUT','/admin/bookings/'+booking+'/'+decision,decision==='reject'?{admin_remarks:'Load test rejection'}:{},{operation:decision+'_booking',expected:[302]});
            }
        }else if(client.group==='booking' && client.index%5===2){
            await request(client,'PUT','/staff/bookings/'+client.bookingId+'/cancel',{}, {operation:'cancel_pending',expected:[302]});
        }else await browse(client,round,2);
    });
    await barrier(async client=>{
        if(client.group==='booking' && client.index%5===3)await request(client,'PUT','/staff/bookings/'+client.bookingId+'/cancel',{}, {operation:'cancel_approved',expected:[302]});
        else if(client.group==='admin')await request(client,'GET',completedRounds%2===0?'/admin/reports/monthly?month='+fixtureData.base_date.slice(0,7):'/admin/analytics?month='+fixtureData.base_date.slice(0,7),null,{operation:completedRounds%2===0?'monthly_report':'facility_analytics'});
        else await browse(client,round,3);
    });
}
function jsonError(response,key){try{return Boolean(JSON.parse(response.body).errors?.[key]);}catch{return false;}}
async function bookingRace() {
    const responses=[];
    await barrier(async client=>{
        if(client.group==='booking'){
            const response=await request(client,'POST','/staff/bookings',{facility_id:fixtureData.facilities[35],start_date:fixtureData.race_date,end_date:fixtureData.race_date,start_time:'11:00',end_time:'12:00',purpose:'booking-race'},{phase:'booking-race',operation:'compete_for_same_slot',expected:[302,422]});
            responses.push(response);
            if(response.status===422 && !jsonError(response,'conflict'))recordFailure('Race loser did not receive conflict validation.');
        }else await request(client,'GET',client.group==='admin'?'/admin/bookings/manage?tab=pending':'/dashboard',null,{phase:'booking-race',operation:'race_observer'});
    });
    if(responses.filter(x=>x.status===302).length!==1 || responses.filter(x=>x.status===422).length!==24)recordFailure('Expected one booking winner and 24 conflicts.');
}
async function adminRace() {
    const responses=[];
    await barrier(async client=>{
        if(client.group==='admin'){
            const response=await request(client,'PUT','/admin/bookings/'+fixtureData.admin_race_id+'/approve',{}, {phase:'admin-race',operation:'compete_for_decision',expected:[302,422]});
            responses.push(response);
            if(response.status===422 && !jsonError(response,'status'))recordFailure('Duplicate decision did not return stale-status validation.');
        }else await request(client,'GET','/dashboard',null,{phase:'admin-race',operation:'race_observer'});
    });
    if(responses.filter(x=>x.status===302).length!==1 || responses.filter(x=>x.status===422).length!==4)recordFailure('Expected one admin winner and four rejected decisions.');
}
async function securityAndExports() {
    const browser=clients[25],admin=clients[45],owner=clients[0];
    await request(browser,'GET','/admin/bookings',null,{phase:'security',operation:'staff_admin_denied',expected:[403]});
    await request(browser,'GET','/bookings/'+fixtureData.admin_race_id,null,{phase:'security',operation:'other_owner_denied',expected:[403]});
    await request(browser,'PUT','/staff/bookings/'+fixtureData.admin_race_id+'/cancel',{}, {phase:'security',operation:'other_cancel_denied',expected:[403]});
    await request(owner,'POST','/staff/bookings',bookingData(completedRounds+1,0,'csrf-must-not-create'),{phase:'security',operation:'missing_csrf_denied',expected:[419],csrf:false});
    const csv=await request(admin,'GET','/admin/bookings/export?q=mixed%3A',null,{phase:'exports',operation:'csv_export'});
    if(!csv.body.includes('Facility,Booker') || !csv.body.includes('mixed:0:0'))recordFailure('CSV export is missing expected records.');
    const analyticsCsv=await request(admin,'GET','/admin/analytics/export?month='+fixtureData.base_date.slice(0,7),null,{phase:'exports',operation:'analytics_csv_export'});
    if(!analyticsCsv.body.includes('Approved bookings') || !analyticsCsv.body.includes('Load Room'))recordFailure('Facility analytics CSV missing expected headers or facilities.');
    await request(browser,'GET','/admin/analytics',null,{phase:'security',operation:'staff_analytics_denied',expected:[403]});
    const pdf=await request(admin,'GET','/admin/reports/monthly/download?month='+fixtureData.base_date.slice(0,7),null,{phase:'exports',operation:'pdf_export'});
    if(!pdf.body.startsWith('%PDF-'))recordFailure('Monthly report was not a PDF.');
}
function statistics(rows) {
    const values=rows.map(x=>x.ms).sort((a,b)=>a-b), quantile=p=>values[Math.max(0,Math.ceil(values.length*p)-1)] || 0;
    const round=n=>Math.round(n*100)/100;
    return {requests:rows.length,p50_ms:round(quantile(.5)),p95_ms:round(quantile(.95)),p99_ms:round(quantile(.99)),max_ms:round(values.at(-1)||0),response_bytes:rows.reduce((n,x)=>n+x.bytes,0)};
}
async function stopServer(){
    if(!server || server.exitCode!==null)return;
    try{process.kill(-server.pid,'SIGTERM');}catch{}
    await Promise.race([new Promise(r=>server.once('exit',r)),pause(2000)]);
    try{process.kill(-server.pid,'SIGKILL');}catch{}
}
try {
    console.log('Preparing 50 synthetic accounts and '+options.history+' history records in an isolated SQLite database.');
    fixtureData=fixture('prepare',{password,history:options.history});
    const port=await new Promise((accept,reject)=>{const socket=net.createServer();socket.on('error',reject);socket.listen(0,'127.0.0.1',()=>{const port=socket.address().port;socket.close(()=>accept(port));});});
    environment.APP_URL='http://127.0.0.1:'+port;
    environment.PHP_CLI_SERVER_WORKERS=String(options.workers);
    logHandle=openSync(join(run,'server.log'),'w',0o600);
    server=spawn(php,[...(options.memory?['-d','memory_limit='+options.memory]:[]),'-S','127.0.0.1:'+port,'-t',join(project,'public'),join(here,'router.php')],{cwd:project,env:environment,stdio:['ignore',logHandle,logHandle],detached:true});
    server.on('error',error=>recordFailure('Server failed to start: '+error.message));
    const health=makeClient({index:50,group:'health'});
    let ready=false;
    for(let attempt=0;attempt<100;attempt++){
        const count=failures.length;
        const response=await request(health,'GET','/up',null,{phase:'health',operation:'health'});
        if(response.status===200){ready=true;break;}
        failures.splice(count);await pause(100);
    }
    health.agent.destroy();if(!ready)throw new Error('Isolated PHP server did not become ready.');
    clients=fixtureData.users.map(makeClient);
    console.log('Authenticating 25 booking staff, 20 browsing staff and five administrators through real login + CSRF flows.');
    await Promise.all(clients.map(authenticate));
    console.log('Starting synchronized 50-user mixed workload.');
    workloadStart=performance.now();
    do {
        await mixedRound(completedRounds);completedRounds++;
        if(interrupted)throw new Error('Load run interrupted.');
        if(completedRounds%5===0)console.log('Completed '+completedRounds+' rounds; '+Math.round((performance.now()-workloadStart)/1000)+'s elapsed.');
    }while((!options.duration || (performance.now()-workloadStart)<options.duration*1000) && (!options.rounds || completedRounds<options.rounds));
    mixedSeconds=(performance.now()-workloadStart)/1000;
    console.log('Running synchronized booking and admin decision races.');
    await bookingRace();await adminRace();await securityAndExports();
    workloadEnd=performance.now();
    const integrity=fixture('verify',{rounds:completedRounds,history:options.history,admin_race_id:fixtureData.admin_race_id});
    for(const error of integrity.errors)recordFailure(error);
    if(peakInFlight!==50 || peakActiveUsers!==50)recordFailure('The harness did not observe all 50 users/concurrent requests.');
    const protectedUnchanged = protectedPaths.every((path,index)=>fingerprint(path)===protectedBefore[index]);
    if(!protectedUnchanged)recordFailure('A protected project file changed during the run.');
    const measured=samples.filter(x=>!['health','warmup'].includes(x.phase));
    const performanceRows=measured.filter(x=>x.phase!=='security');
    const aggregate=statistics(performanceRows);
    const budget={p95_ms:options.p95,p99_ms:options.p99,passed:aggregate.p95_ms<=options.p95 && aggregate.p99_ms<=options.p99};
    if(!budget.passed)recordFailure('Proposed latency budget exceeded.');
    const groups=Object.fromEntries(['booking','browsing','admin'].map(group=>[group,statistics(measured.filter(x=>x.group===group))]));
    const operations=Object.fromEntries([...new Set(measured.map(x=>x.operation))].map(operation=>[operation,statistics(measured.filter(x=>x.operation===operation))]));
    const statusCounts=Object.fromEntries([...new Set(measured.map(x=>x.status))].map(status=>[status,measured.filter(x=>x.status===status).length]));
    report={passed:failures.length===0,generated_at:new Date().toISOString(),profile:'local PHP multi-worker development server; disposable SQLite WAL; sync queue; array mail; file cache/session',environment:{php:fixtureData.php,laravel:fixtureData.laravel,node:process.version,platform:platform(),logical_cpus:cpus().length,configured_php_workers:options.workers,observed_serving_processes:workerPids.size,client_ip_model:platform()==='linux'?'one distinct loopback IP per user':'shared loopback IP; warmup respects login throttling',php_memory_limit:observedMemoryLimit,bcrypt_rounds:fixtureData.bcrypt_rounds,timezone:fixtureData.timezone},users:{total:50,booking_staff:25,browsing_staff:20,administrators:5,peak_active_virtual_users:peakActiveUsers,peak_in_flight_http:peakInFlight},workload:{requested_duration_seconds:options.duration,mixed_duration_seconds:Math.round(mixedSeconds*100)/100,completed_rounds:completedRounds,seed_history:options.history,total_measured_seconds:Math.round((workloadEnd-workloadStart)/10)/100,measured_requests:measured.length,requests_per_second:Math.round(measured.length/((workloadEnd-workloadStart)/1000)*100)/100},protected_source_files_unchanged:protectedUnchanged,unexpected_http_responses:measured.filter(x=>x.unexpected).length,failed_checks:failures.length,latency:aggregate,budget,groups,operations,http_statuses:statusCounts,expected_conflicts:{booking:24,admin_decision:4},warmup:statistics(samples.filter(x=>x.phase==='warmup')),integrity,failures};
} catch(error){
    recordFailure(error.message);
    report={passed:false,generated_at:new Date().toISOString(),completed_rounds:completedRounds,failures};
} finally {
    for(const client of clients)client.agent.destroy();await stopServer();if(logHandle!==undefined)closeSync(logHandle);
    mkdirSync(options.output,{recursive:true});
    writeFileSync(join(options.output,'summary.json'),JSON.stringify(report,null,2)+'\n');
    writeFileSync(join(options.output,'requests.csv'),'phase,operation,group,user,status,expected_statuses,unexpected,duration_ms,response_bytes\n'+samples.filter(x=>x.phase!=='health').map(x=>[x.phase,x.operation,x.group,x.user,x.status,x.expected,x.unexpected,x.ms.toFixed(3),x.bytes].map(x=>'"'+String(x).replaceAll('"','""')+'"').join(',')).join('\n')+'\n');
    const md = report.workload ? `# IPG 50-user load-test result

Result: **${report.passed?'PASS':'FAIL'}**

| Measure | Result |
| --- | --- |
| Users | 25 booking staff + 20 browsing staff + 5 admins |
| Mixed workload duration | ${report.workload.mixed_duration_seconds}s |
| Mixed rounds | ${report.workload.completed_rounds} |
| Measured HTTP requests | ${report.workload.measured_requests} |
| Throughput including race/export checks | ${report.workload.requests_per_second}/s |
| p50 / p95 / p99 | ${report.latency.p50_ms} / ${report.latency.p95_ms} / ${report.latency.p99_ms} ms |
| Peak in-flight HTTP requests | ${report.users.peak_in_flight_http} |
| Unexpected HTTP responses | ${report.unexpected_http_responses} |\n| Failed checks | ${report.failed_checks} |
| Overlapping active bookings | ${report.integrity.overlaps.length} |
| Slot race | ${report.integrity.booking_race_winners} winner, 24 expected conflicts |
| Admin race | ${report.integrity.admin_race_decision_events} decision, 4 expected duplicate rejections |
| Protected project files unchanged | ${report.protected_source_files_unchanged} |

## Mixed booking outcomes

| Status | Count |
| --- | --- |
${Object.entries(report.integrity.mixed_statuses).map(([k,v])=>'| '+k+' | '+v+' |').join('\n')}

Profile: ${report.profile}. Configured workers: ${options.workers}; observed serving processes (including parent): ${workerPids.size}. Warmup/login requests are reported separately. Expected 422 conflicts and deliberate 403/419 checks are not counted as application failures. Latency budgets are proposed test defaults, not a production SLA. HTTP timings measure full server responses, not browser rendering or static assets. This is a synchronized closed workload, not an open-arrival capacity test.

${report.failures.map(x=>'- '+x.message).join('\n')}
` : '# Load test failed before measurement\n\n'+report.failures.map(x=>'- '+x.message).join('\n')+'\n';
    writeFileSync(join(options.output,'summary.md'),md);
    if(report.passed){rmSync(run,{recursive:true,force:true});rmSync(join(options.output,'server.log'),{force:true});}
    else {writeFileSync(join(options.output,'server.log'),readFileSync(join(run,'server.log'),{encoding:'utf8',flag:'a+'}));console.log('Failure workspace retained at '+run+' (synthetic records only).');}
    console.log(JSON.stringify(report,null,2));console.log('Results: '+options.output);
    process.exitCode=report.passed?0:1;
}
